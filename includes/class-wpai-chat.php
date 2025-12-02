<?php
/**
 * Chat functionality for WP AI Assistant
 */

if (!defined('ABSPATH')) {
    exit;
}

class WPAI_Chat {
    
    private $api_manager;
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
        
        $this->init_hooks();
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
    }
    
    /**
     * Send message
     */
    public function send_message($message, $session_id = null, $context = array(), $settings = array()) {
        global $wpdb;
        
        if (!$session_id) {
            $session_id = $this->generate_session_id();
        }
        
        $user_id = get_current_user_id();
        
        // Build messages array
        $messages = $this->build_messages($session_id, $message, $context);
        
        // Send to API
        $response = $this->api_manager->send_request($messages, $settings);
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        // Save to database
        $wpdb->insert(
            $this->table_name,
            array(
                'user_id' => $user_id,
                'session_id' => $session_id,
                'message' => $message,
                'response' => $response,
                'model' => get_option('wpai_default_model', 'gpt-3.5-turbo'),
                'settings' => json_encode($settings),
                'created_at' => current_time('mysql'),
            ),
            array('%d', '%s', '%s', '%s', '%s', '%s', '%s')
        );
        
        return array(
            'session_id' => $session_id,
            'response' => $response,
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
        
        // Get chat history
        $history = $this->get_chat_history($session_id, 10); // Last 10 messages
        
        foreach ($history as $item) {
            $messages[] = array(
                'role' => 'user',
                'content' => $item->message,
            );
            if (!empty($item->response)) {
                $messages[] = array(
                    'role' => 'assistant',
                    'content' => $item->response,
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
}

