<?php
/**
 * Content Generator for creating/editing posts and pages
 */

if (!defined('ABSPATH')) {
    exit;
}

class WPAI_Content_Generator {
    
    private $api_manager;
    private $security;
    public $intent_detector; // Made public for access in view files
    
    public function __construct($api_manager = null, $security = null) {
        // Require dependencies to be passed to avoid circular dependency
        // These should always be provided when called from WPAI_Plugin::init_components()
        if (!$api_manager || !$security) {
            // This should not happen in normal flow, but provide fallback for safety
            $plugin = WPAI_Plugin::get_instance();
            if ($plugin) {
                if (!$api_manager && isset($plugin->api)) {
                    $this->api_manager = $plugin->api;
                } else {
                    $this->api_manager = $api_manager;
                }
                
                if (!$security && isset($plugin->security)) {
                    $this->security = $plugin->security;
                } else {
                    $this->security = $security;
                }
            } else {
                // Plugin not initialized - this should not happen
                $this->api_manager = $api_manager;
                $this->security = $security;
            }
        } else {
            $this->api_manager = $api_manager;
            $this->security = $security;
        }
        
        // Pass api_manager to intent_detector to avoid circular dependency
        $this->intent_detector = new WPAI_Intent_Detector($this->api_manager);
        $this->init_hooks();
    }
    
    /**
     * Initialize hooks
     */
    private function init_hooks() {
        add_action('wp_ajax_wpai_generate_content', array($this, 'ajax_generate_content'));
        add_action('wp_ajax_wpai_apply_content', array($this, 'ajax_apply_content'));
        add_action('wp_ajax_wpai_preview_content', array($this, 'ajax_preview_content'));
    }
    
    /**
     * Generate content
     */
    public function generate_content($prompt, $post_type = 'post', $context = array(), $settings = array()) {
        // Build system prompt
        $system_prompt = $this->build_system_prompt($post_type, $context);
        
        // Add topic initial data if available
        if (!empty($context['topic_id'])) {
            $topic_manager = new WPAI_Topic_Manager();
            $initial_data = $topic_manager->get_initial_data($context['topic_id']);
            if (!empty($initial_data)) {
                $system_prompt .= "\n\nContext and guidelines:\n" . $initial_data;
            }
        }
        
        // Build messages
        $messages = array(
            array(
                'role' => 'system',
                'content' => $system_prompt,
            ),
            array(
                'role' => 'user',
                'content' => $prompt,
            ),
        );
        
        // Add context from uploaded files
        if (!empty($context['file_context'])) {
            $messages[] = array(
                'role' => 'user',
                'content' => 'Additional context from files: ' . $context['file_context'],
            );
        }
        
        // Send request
        $response = $this->api_manager->send_request($messages, $settings);
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        return $response;
    }
    
    /**
     * Build system prompt
     */
    private function build_system_prompt($post_type, $context) {
        $prompt = "You are a WordPress content generator. ";
        
        if ($post_type === 'page') {
            $prompt .= "Generate content for a WordPress page. ";
        } else {
            $prompt .= "Generate content for a WordPress post. ";
        }
        
        $prompt .= "The content should be well-structured, SEO-friendly, and engaging. ";
        
        // Add format requirements
        if (!empty($context['format'])) {
            switch ($context['format']) {
                case 'elementor':
                    $prompt .= "Format the content for Elementor page builder with appropriate sections and widgets. ";
                    break;
                case 'gutenberg':
                    $prompt .= "Format the content using Gutenberg blocks (use HTML comments for block markers). ";
                    break;
                case 'classic':
                    $prompt .= "Format the content as classic HTML. ";
                    break;
            }
        }
        
        $prompt .= "Respond with the content only, without additional explanations.";
        
        return $prompt;
    }
    
    /**
     * Apply content to post/page
     */
    public function apply_content($post_id, $content, $dry_run = true) {
        if ($dry_run) {
            // Return preview without applying
            return array(
                'success' => true,
                'preview' => $content,
                'dry_run' => true,
            );
        }
        
        // Create backup
        $backup = $this->security->create_backup($post_id);
        
        if (is_wp_error($backup)) {
            return $backup;
        }
        
        // Get current post
        $post = get_post($post_id);
        if (!$post) {
            return new WP_Error('post_not_found', __('Post not found', 'wpai-assistant'));
        }
        
        // Update post content
        $update_data = array(
            'ID' => $post_id,
            'post_content' => $content,
        );
        
        // If auto-publish is disabled, save as draft
        $require_approval = get_option('wpai_require_approval', true);
        if ($require_approval) {
            $update_data['post_status'] = 'draft';
        }
        
        $result = wp_update_post($update_data, true);
        
        if (is_wp_error($result)) {
            return $result;
        }
        
        // Log action
        $this->security->log_action('apply_content', 'post', $post_id, array(
            'backup_id' => $backup,
            'content_length' => strlen($content),
        ));
        
        return array(
            'success' => true,
            'post_id' => $post_id,
            'backup_id' => $backup,
        );
    }
    
    /**
     * Generate post/page with metadata
     */
    public function generate_post($title, $content, $post_type = 'post', $meta = array()) {
        $post_data = array(
            'post_title' => sanitize_text_field($title),
            'post_content' => wp_kses_post($content),
            'post_type' => $post_type,
            'post_status' => get_option('wpai_require_approval', true) ? 'draft' : 'publish',
            'post_author' => get_current_user_id(),
        );
        
        // Add categories if provided
        if (!empty($meta['categories']) && $post_type === 'post') {
            $post_data['post_category'] = array_map('intval', $meta['categories']);
        }
        
        // Add tags if provided
        if (!empty($meta['tags']) && $post_type === 'post') {
            $post_data['tags_input'] = $meta['tags'];
        }
        
        $post_id = wp_insert_post($post_data, true);
        
        if (is_wp_error($post_id)) {
            return $post_id;
        }
        
        // Set featured image if provided
        if (!empty($meta['featured_image'])) {
            set_post_thumbnail($post_id, intval($meta['featured_image']));
        }
        
        // Add custom meta
        if (!empty($meta['meta'])) {
            foreach ($meta['meta'] as $key => $value) {
                update_post_meta($post_id, $key, $value);
            }
        }
        
        // Log action
        $this->security->log_action('generate_post', $post_type, $post_id, array(
            'title' => $title,
        ));
        
        return $post_id;
    }
    
    /**
     * Convert content to Gutenberg blocks
     */
    public function convert_to_blocks($content) {
        // Simple conversion - in production, use proper block parser
        $blocks = array();
        
        // Split by paragraphs
        $paragraphs = explode("\n\n", $content);
        
        foreach ($paragraphs as $para) {
            $para = trim($para);
            if (empty($para)) {
                continue;
            }
            
            // Check if it's a heading
            if (preg_match('/^#+\s+(.+)$/', $para, $matches)) {
                $level = strlen($matches[0]) - strlen(ltrim($matches[0], '#'));
                $blocks[] = "<!-- wp:heading {\"level\":{$level}} -->\n<h{$level}>" . esc_html($matches[1]) . "</h{$level}>\n<!-- /wp:heading -->";
            } else {
                $blocks[] = "<!-- wp:paragraph -->\n<p>" . wp_kses_post($para) . "</p>\n<!-- /wp:paragraph -->";
            }
        }
        
        return implode("\n\n", $blocks);
    }
    
    /**
     * AJAX: Generate content
     */
    public function ajax_generate_content() {
        check_ajax_referer('wpai_nonce', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(array('message' => __('Permission denied', 'wpai-assistant')));
        }
        
        $prompt = sanitize_textarea_field($_POST['prompt'] ?? '');
        $post_type = sanitize_text_field($_POST['post_type'] ?? 'post');
        $context = json_decode(stripslashes($_POST['context'] ?? '{}'), true);
        $settings = json_decode(stripslashes($_POST['settings'] ?? '{}'), true);
        
        if (empty($prompt)) {
            wp_send_json_error(array('message' => __('Prompt is required', 'wpai-assistant')));
        }
        
        $content = $this->generate_content($prompt, $post_type, $context, $settings);
        
        if (is_wp_error($content)) {
            wp_send_json_error(array('message' => $content->get_error_message()));
        }
        
        wp_send_json_success(array('content' => $content));
    }
    
    /**
     * AJAX: Apply content
     */
    public function ajax_apply_content() {
        check_ajax_referer('wpai_nonce', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(array('message' => __('Permission denied', 'wpai-assistant')));
        }
        
        $post_id = intval($_POST['post_id'] ?? 0);
        $content = wp_kses_post($_POST['content'] ?? '');
        $dry_run = isset($_POST['dry_run']) ? (bool) $_POST['dry_run'] : true;
        
        if (empty($content)) {
            wp_send_json_error(array('message' => __('Content is required', 'wpai-assistant')));
        }
        
        $result = $this->apply_content($post_id, $content, $dry_run);
        
        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        }
        
        wp_send_json_success($result);
    }
    
    /**
     * AJAX: Preview content
     */
    public function ajax_preview_content() {
        check_ajax_referer('wpai_nonce', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(array('message' => __('Permission denied', 'wpai-assistant')));
        }
        
        $content = wp_kses_post($_POST['content'] ?? '');
        $format = sanitize_text_field($_POST['format'] ?? 'classic');
        
        if (empty($content)) {
            wp_send_json_error(array('message' => __('Content is required', 'wpai-assistant')));
        }
        
        // Convert to blocks if needed
        if ($format === 'gutenberg') {
            $content = $this->convert_to_blocks($content);
        }
        
        wp_send_json_success(array(
            'preview' => $content,
            'html' => apply_filters('the_content', $content),
        ));
    }
}

