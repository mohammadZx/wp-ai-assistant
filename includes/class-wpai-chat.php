<?php
/**
 * Chat functionality for WP AI Assistant
 */

if (!defined('ABSPATH')) {
    exit;
}

class WPAI_Chat {
    
    private $api_manager;
    private $function_handler;
    private $table_name;
    
    public function __construct($api_manager = null) {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'wpai_chats';
        
        // Require api_manager to be passed to avoid circular dependency
        if ($api_manager) {
            $this->api_manager = $api_manager;
        } else {
            // Fallback only if plugin is already initialized (should not happen in normal flow)
            $plugin = WPAI_Plugin::get_instance();
            if ($plugin && isset($plugin->api)) {
                $this->api_manager = $plugin->api;
            }
        }
        
        // Don't initialize function_handler here to avoid circular dependency
        // It will be lazy-loaded when needed
        
        $this->init_hooks();
    }
    
    /**
     * Get function handler (lazy loading to avoid circular dependency)
     */
    private function get_function_handler() {
        if ($this->function_handler === null) {
            // Only initialize after plugin is fully loaded
            $plugin = WPAI_Plugin::get_instance();
            if ($plugin && isset($plugin->content_generator) && isset($plugin->security)) {
                $this->function_handler = new WPAI_Function_Handler(
                    $plugin->content_generator,
                    $plugin->security
                );
            }
        }
        return $this->function_handler;
    }
    
    /**
     * Initialize hooks
     */
    private function init_hooks() {
        add_action('wp_ajax_wpai_send_message', array($this, 'ajax_send_message'));
        add_action('wp_ajax_wpai_get_chat_history', array($this, 'ajax_get_chat_history'));
        add_action('wp_ajax_wpai_export_chat', array($this, 'ajax_export_chat'));
        add_action('wp_ajax_wpai_import_chat', array($this, 'ajax_import_chat'));
        add_action('wp_ajax_wpai_clear_chat', array($this, 'ajax_clear_chat'));
        add_action('wp_ajax_wpai_search_posts', array($this, 'ajax_search_posts'));
    }
    
    /**
     * Send message with function calling support
     */
    public function send_message($message, $session_id = null, $context = array(), $settings = array()) {
        global $wpdb;
        
        if (!$session_id) {
            $session_id = $this->generate_session_id();
        }
        
        $user_id = get_current_user_id();
        
        // Build messages array
        $messages = $this->build_messages($session_id, $message, $context);
        
        // Get functions for function calling (lazy load handler)
        $functions = null;
        $function_handler = $this->get_function_handler();
        if ($function_handler) {
            $functions = $function_handler->get_functions();
        }
        
        // Handle selected post from context
        if (!empty($context['selected_post_id'])) {
            $selected_post_id = intval($context['selected_post_id']);
            $post = get_post($selected_post_id);
            if ($post) {
                // Add post context to system message
                $post_info = sprintf(
                    __('Current selected post: ID %d, Title: %s, Type: %s', 'wpai-assistant'),
                    $post->ID,
                    $post->post_title,
                    $post->post_type
                );
                array_unshift($messages, array(
                    'role' => 'system',
                    'content' => $post_info,
                ));
            }
        }
        
        // Send to API with function calling
        $response = $this->api_manager->send_request($messages, $settings, $functions);
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        $final_response = '';
        $function_calls_made = array();
        $max_iterations = 5; // Reduced to prevent memory issues - Prevent infinite loops
        $iteration = 0;
        $max_messages = 50; // Limit total messages to prevent memory exhaustion
        
        // Handle function calling loop
        while ($iteration < $max_iterations && count($messages) < $max_messages) {
            $iteration++;
            
            // Check if response is a function call
            if (is_array($response) && isset($response['type']) && $response['type'] === 'function_call') {
                $function_call = $response['function_call'] ?? null;
                
                if (!$function_call || empty($function_call['name'] ?? '')) {
                    // Invalid function call, break
                    break;
                }
                
                $function_name = $function_call['name'];
                $function_args = isset($function_call['arguments']) ? json_decode($function_call['arguments'], true) : array();
                
                // Safety check: if json_decode failed, use empty array
                if (!is_array($function_args)) {
                    $function_args = array();
                }
                
                // Execute function (lazy load handler)
                $function_handler = $this->get_function_handler();
                if (!$function_handler) {
                    $function_result = new WP_Error('function_handler_unavailable', __('Function handler is not available', 'wpai-assistant'));
                } else {
                    $function_result = $function_handler->execute_function($function_name, $function_args);
                }
                
                // Store function call info (limit result size to prevent memory issues)
                $result_summary = $function_result;
                if (is_array($function_result) && count($function_result) > 10) {
                    // Limit array size
                    $result_summary = array_slice($function_result, 0, 10);
                    $result_summary['_truncated'] = true;
                }
                
                $function_calls_made[] = array(
                    'name' => $function_name,
                    'arguments' => $function_args,
                    'result' => $result_summary,
                );
                
                // Limit function calls stored
                if (count($function_calls_made) >= 10) {
                    break;
                }
                
                // Add function call and result to messages
                $messages[] = array(
                    'role' => 'assistant',
                    'function_call' => $function_call,
                );
                
                // Limit function result JSON size
                $function_result_json = '';
                if (is_wp_error($function_result)) {
                    $function_result_json = json_encode(array('error' => $function_result->get_error_message()));
                } else {
                    $function_result_json = json_encode($function_result);
                    // If JSON is too large, truncate
                    if (strlen($function_result_json) > 50000) {
                        $function_result_json = json_encode(array(
                            'success' => isset($function_result['success']) ? $function_result['success'] : false,
                            'message' => __('Result too large, truncated', 'wpai-assistant'),
                            '_truncated' => true,
                        ));
                    }
                }
                
                $messages[] = array(
                    'role' => 'function',
                    'name' => $function_name,
                    'content' => $function_result_json,
                );
                
                // Send back to API for final response
                $response = $this->api_manager->send_request($messages, $settings, $functions);
                
                if (is_wp_error($response)) {
                    return $response;
                }
            } else {
                // Regular content response
                if (is_array($response) && isset($response['content'])) {
                    $final_response = $response['content'];
                } elseif (is_string($response)) {
                    $final_response = $response;
                }
                break;
            }
        }
        
        // If we still have function calls after max iterations, format response
        if ($iteration >= $max_iterations || count($messages) >= $max_messages) {
            if (is_array($response) && isset($response['type']) && $response['type'] === 'function_call') {
                $final_response = __('Function calls limit reached. Some actions may not be completed.', 'wpai-assistant');
            }
        }
        
        // Format final response with function call results
        if (!empty($function_calls_made)) {
            $function_summary = "\n\n" . __('Actions taken:', 'wpai-assistant') . "\n";
            foreach ($function_calls_made as $call) {
                $function_summary .= "- " . $call['name'] . "\n";
                if (is_array($call['result']) && isset($call['result']['success'])) {
                    if (isset($call['result']['post_id'])) {
                        $function_summary .= "  " . sprintf(__('Post ID: %d', 'wpai-assistant'), $call['result']['post_id']) . "\n";
                    }
                    if (isset($call['result']['edit_link'])) {
                        $function_summary .= "  " . __('Edit link available', 'wpai-assistant') . "\n";
                    }
                }
            }
            $final_response .= $function_summary;
        }
        
        // Save to database (limit size to prevent memory issues)
        $response_text = $final_response;
        if (!empty($function_calls_made)) {
            // Limit function calls summary size
            $function_summary = array_slice($function_calls_made, 0, 5); // Only store first 5
            $response_text .= "\n\n" . __('Function calls:', 'wpai-assistant') . " " . json_encode($function_summary);
            if (count($function_calls_made) > 5) {
                $response_text .= "\n" . sprintf(__('... and %d more', 'wpai-assistant'), count($function_calls_made) - 5);
            }
        }
        
        // Limit total response text size
        if (strlen($response_text) > 100000) {
            $response_text = substr($response_text, 0, 100000) . "\n\n" . __('[Response truncated due to size]', 'wpai-assistant');
        }
        
        $wpdb->insert(
            $this->table_name,
            array(
                'user_id' => $user_id,
                'session_id' => $session_id,
                'message' => $message,
                'response' => $response_text,
                'model' => get_option('wpai_default_model', 'gpt-3.5-turbo'),
                'settings' => json_encode($settings),
                'created_at' => current_time('mysql'),
            ),
            array('%d', '%s', '%s', '%s', '%s', '%s', '%s')
        );
        
        return array(
            'session_id' => $session_id,
            'response' => $final_response,
            'function_calls' => $function_calls_made,
            'message_id' => $wpdb->insert_id,
        );
    }
    
    /**
     * Build messages array from history and new message
     */
    private function build_messages($session_id, $new_message, $context = array()) {
        $messages = array();
        
        // Add system context if provided
        if (!empty($context['system'])) {
            $messages[] = array(
                'role' => 'system',
                'content' => $context['system'],
            );
        }
        
        // Add topic initial data if provided
        if (!empty($context['topic_data'])) {
            $messages[] = array(
                'role' => 'system',
                'content' => 'Context: ' . $context['topic_data'],
            );
        }
        
        // Get chat history (limit to prevent memory issues)
        $history = $this->get_chat_history($session_id, 15); // Last 15 messages for better context (reduced from 20)
        
        foreach ($history as $item) {
            $messages[] = array(
                'role' => 'user',
                'content' => $item->message,
            );
            if (!empty($item->response)) {
                // Try to parse function calls from response
                $response_content = $item->response;
                if (strpos($response_content, 'Function calls:') !== false) {
                    // Extract just the text part before function calls
                    $parts = explode('Function calls:', $response_content);
                    $response_content = trim($parts[0]);
                }
                $messages[] = array(
                    'role' => 'assistant',
                    'content' => $response_content,
                );
            }
        }
        
        // Add new message
        $messages[] = array(
            'role' => 'user',
            'content' => $new_message,
        );
        
        return $messages;
    }
    
    /**
     * Get chat history
     */
    public function get_chat_history($session_id, $limit = 50) {
        global $wpdb;
        
        // Safety: limit to prevent memory issues
        $limit = min($limit, 20); // Maximum 20 messages
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$this->table_name} 
            WHERE session_id = %s 
            ORDER BY created_at ASC 
            LIMIT %d",
            $session_id,
            $limit
        ));
    }
    
    /**
     * Get user sessions
     */
    public function get_user_sessions($user_id = null) {
        global $wpdb;
        
        if (!$user_id) {
            $user_id = get_current_user_id();
        }
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT DISTINCT session_id, MAX(created_at) as last_message 
            FROM {$this->table_name} 
            WHERE user_id = %d 
            GROUP BY session_id 
            ORDER BY last_message DESC",
            $user_id
        ));
    }
    
    /**
     * Generate session ID
     */
    private function generate_session_id() {
        return 'wpai_' . wp_generate_uuid4();
    }
    
    /**
     * AJAX: Send message
     */
    public function ajax_send_message() {
        check_ajax_referer('wpai_nonce', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(array('message' => __('Permission denied', 'wpai-assistant')));
        }
        
        $message = sanitize_text_field($_POST['message'] ?? '');
        $session_id = sanitize_text_field($_POST['session_id'] ?? '');
        $context = json_decode(stripslashes($_POST['context'] ?? '{}'), true);
        $settings = json_decode(stripslashes($_POST['settings'] ?? '{}'), true);
        
        if (empty($message)) {
            wp_send_json_error(array('message' => __('Message is required', 'wpai-assistant')));
        }
        
        $result = $this->send_message($message, $session_id, $context, $settings);
        
        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        }
        
        wp_send_json_success($result);
    }
    
    /**
     * AJAX: Get chat history
     */
    public function ajax_get_chat_history() {
        check_ajax_referer('wpai_nonce', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(array('message' => __('Permission denied', 'wpai-assistant')));
        }
        
        $session_id = sanitize_text_field($_POST['session_id'] ?? '');
        
        if (empty($session_id)) {
            wp_send_json_error(array('message' => __('Session ID is required', 'wpai-assistant')));
        }
        
        $history = $this->get_chat_history($session_id);
        
        wp_send_json_success(array('history' => $history));
    }
    
    /**
     * AJAX: Export chat
     */
    public function ajax_export_chat() {
        check_ajax_referer('wpai_nonce', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(array('message' => __('Permission denied', 'wpai-assistant')));
        }
        
        $session_id = sanitize_text_field($_POST['session_id'] ?? '');
        
        if (empty($session_id)) {
            wp_send_json_error(array('message' => __('Session ID is required', 'wpai-assistant')));
        }
        
        $history = $this->get_chat_history($session_id);
        
        $export = array(
            'session_id' => $session_id,
            'exported_at' => current_time('mysql'),
            'messages' => $history,
        );
        
        wp_send_json_success(array('export' => json_encode($export, JSON_PRETTY_PRINT)));
    }
    
    /**
     * AJAX: Import chat
     */
    public function ajax_import_chat() {
        check_ajax_referer('wpai_nonce', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(array('message' => __('Permission denied', 'wpai-assistant')));
        }
        
        $import_data = json_decode(stripslashes($_POST['import_data'] ?? ''), true);
        
        if (empty($import_data) || !isset($import_data['messages'])) {
            wp_send_json_error(array('message' => __('Invalid import data', 'wpai-assistant')));
        }
        
        global $wpdb;
        $user_id = get_current_user_id();
        $session_id = $this->generate_session_id();
        
        foreach ($import_data['messages'] as $msg) {
            $wpdb->insert(
                $this->table_name,
                array(
                    'user_id' => $user_id,
                    'session_id' => $session_id,
                    'message' => $msg->message ?? '',
                    'response' => $msg->response ?? '',
                    'model' => $msg->model ?? '',
                    'settings' => $msg->settings ?? '',
                    'created_at' => $msg->created_at ?? current_time('mysql'),
                ),
                array('%d', '%s', '%s', '%s', '%s', '%s', '%s')
            );
        }
        
        wp_send_json_success(array('session_id' => $session_id));
    }
    
    /**
     * AJAX: Clear chat
     */
    public function ajax_clear_chat() {
        check_ajax_referer('wpai_nonce', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(array('message' => __('Permission denied', 'wpai-assistant')));
        }
        
        $session_id = sanitize_text_field($_POST['session_id'] ?? '');
        
        if (empty($session_id)) {
            wp_send_json_error(array('message' => __('Session ID is required', 'wpai-assistant')));
        }
        
        global $wpdb;
        $user_id = get_current_user_id();
        
        $wpdb->delete(
            $this->table_name,
            array(
                'session_id' => $session_id,
                'user_id' => $user_id,
            ),
            array('%s', '%d')
        );
        
        wp_send_json_success();
    }
    
    /**
     * AJAX: Search posts
     */
    public function ajax_search_posts() {
        check_ajax_referer('wpai_nonce', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(array('message' => __('Permission denied', 'wpai-assistant')));
        }
        
        $query = sanitize_text_field($_POST['query'] ?? '');
        $post_type = sanitize_text_field($_POST['post_type'] ?? 'any');
        $limit = intval($_POST['limit'] ?? 10);
        
        if (empty($query)) {
            wp_send_json_error(array('message' => __('Search query is required', 'wpai-assistant')));
        }
        
        $search_args = array(
            'post_type' => $post_type === 'any' ? 'any' : $post_type,
            'posts_per_page' => $limit,
            'post_status' => 'any',
            's' => $query,
            'orderby' => 'relevance',
        );
        
        $posts = get_posts($search_args);
        
        $results = array();
        foreach ($posts as $post) {
            $editor_type = 'gutenberg';
            if (get_post_meta($post->ID, '_elementor_edit_mode', true) === 'builder') {
                $editor_type = 'elementor';
            }
            
            $results[] = array(
                'id' => $post->ID,
                'title' => $post->post_title,
                'type' => $post->post_type,
                'status' => $post->post_status,
                'editor_type' => $editor_type,
                'edit_link' => admin_url('post.php?post=' . $post->ID . '&action=edit'),
                'view_link' => get_permalink($post->ID),
            );
        }
        
        wp_send_json_success(array(
            'count' => count($results),
            'posts' => $results,
        ));
    }
}

