<?php
/**
 * Gutenberg Editor Integration
 */

if (!defined('ABSPATH')) {
    exit;
}

class WPAI_Gutenberg_Integration {
    
    private $plugin;
    
    public function __construct($plugin = null) {
        $this->plugin = $plugin ? $plugin : WPAI_Plugin::get_instance();
        $this->init_hooks();
    }
    
    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // Enqueue scripts for Gutenberg
        add_action('enqueue_block_editor_assets', array($this, 'enqueue_editor_assets'));
        
        // AJAX handlers
        add_action('wp_ajax_wpai_gutenberg_apply', array($this, 'ajax_apply_to_editor'));
        add_action('wp_ajax_wpai_gutenberg_get_content', array($this, 'ajax_get_editor_content'));
        add_action('wp_ajax_wpai_gutenberg_update_blocks', array($this, 'ajax_update_blocks'));
    }
    
    /**
     * Enqueue Gutenberg editor assets
     */
    public function enqueue_editor_assets() {
        $post_id = get_the_ID();
        if (!$post_id) {
            return;
        }
        
        // Check if using Gutenberg
        $screen = get_current_screen();
        if (!$screen || !method_exists($screen, 'is_block_editor') || !$screen->is_block_editor()) {
            return;
        }
        
        wp_enqueue_script(
            'wpai-gutenberg-sidebar',
            WPAI_PLUGIN_URL . 'assets/js/gutenberg-sidebar.js',
            array('wp-plugins', 'wp-edit-post', 'wp-element', 'wp-components', 'wp-data', 'wp-i18n', 'wp-api-fetch'),
            WPAI_VERSION,
            true
        );
        
        wp_enqueue_style(
            'wpai-gutenberg-sidebar',
            WPAI_PLUGIN_URL . 'assets/css/gutenberg-sidebar.css',
            array('wp-edit-blocks'),
            WPAI_VERSION
        );
        
        wp_localize_script('wpai-gutenberg-sidebar', 'wpaiGutenberg', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('wpai_nonce'),
            'postId' => $post_id,
            'postType' => get_post_type($post_id),
            'strings' => array(
                'title' => __('AI Assistant', 'wpai-assistant'),
                'generate' => __('Generate Content', 'wpai-assistant'),
                'improve' => __('Improve Content', 'wpai-assistant'),
                'promptPlaceholder' => __('Describe what you want to generate or improve...', 'wpai-assistant'),
                'generating' => __('Generating...', 'wpai-assistant'),
                'applying' => __('Applying changes...', 'wpai-assistant'),
                'preview' => __('Preview', 'wpai-assistant'),
                'apply' => __('Apply to Editor', 'wpai-assistant'),
                'dismiss' => __('Dismiss', 'wpai-assistant'),
                'error' => __('An error occurred', 'wpai-assistant'),
            )
        ));
    }
    
    /**
     * AJAX: Get current editor content
     */
    public function ajax_get_editor_content() {
        check_ajax_referer('wpai_nonce', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(array('message' => __('Permission denied', 'wpai-assistant')));
        }
        
        $post_id = intval($_POST['post_id'] ?? 0);
        if (!$post_id) {
            wp_send_json_error(array('message' => __('Post ID is required', 'wpai-assistant')));
        }
        
        $post = get_post($post_id);
        if (!$post) {
            wp_send_json_error(array('message' => __('Post not found', 'wpai-assistant')));
        }
        
        // Get blocks
        $blocks = parse_blocks($post->post_content);
        
        wp_send_json_success(array(
            'content' => $post->post_content,
            'blocks' => $blocks,
            'title' => $post->post_title,
        ));
    }
    
    /**
     * AJAX: Apply content to Gutenberg editor
     */
    public function ajax_apply_to_editor() {
        check_ajax_referer('wpai_nonce', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(array('message' => __('Permission denied', 'wpai-assistant')));
        }
        
        $post_id = intval($_POST['post_id'] ?? 0);
        $content = wp_kses_post($_POST['content'] ?? '');
        
        if (!$post_id || empty($content)) {
            wp_send_json_error(array('message' => __('Post ID and content are required', 'wpai-assistant')));
        }
        
        // Update post content
        $result = wp_update_post(array(
            'ID' => $post_id,
            'post_content' => $content,
        ), true);
        
        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        }
        
        wp_send_json_success(array(
            'message' => __('Content applied successfully', 'wpai-assistant'),
            'post_id' => $post_id,
        ));
    }
    
    /**
     * AJAX: Update specific blocks
     */
    public function ajax_update_blocks() {
        check_ajax_referer('wpai_nonce', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(array('message' => __('Permission denied', 'wpai-assistant')));
        }
        
        $post_id = intval($_POST['post_id'] ?? 0);
        $blocks_data = json_decode(stripslashes($_POST['blocks'] ?? '[]'), true);
        
        if (!$post_id) {
            wp_send_json_error(array('message' => __('Post ID is required', 'wpai-assistant')));
        }
        
        $post = get_post($post_id);
        if (!$post) {
            wp_send_json_error(array('message' => __('Post not found', 'wpai-assistant')));
        }
        
        // Get current blocks
        $current_blocks = parse_blocks($post->post_content);
        
        // Update blocks based on provided data
        $updated_blocks = $this->apply_block_updates($current_blocks, $blocks_data);
        
        // Convert blocks back to content
        $new_content = '';
        foreach ($updated_blocks as $block) {
            $new_content .= serialize_block($block);
        }
        
        // Update post
        $result = wp_update_post(array(
            'ID' => $post_id,
            'post_content' => $new_content,
        ), true);
        
        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        }
        
        wp_send_json_success(array(
            'message' => __('Blocks updated successfully', 'wpai-assistant'),
            'content' => $new_content,
        ));
    }
    
    /**
     * Apply block updates
     */
    private function apply_block_updates($current_blocks, $updates) {
        foreach ($updates as $update) {
            $block_index = $update['index'] ?? null;
            $block_name = $update['blockName'] ?? null;
            $new_content = $update['content'] ?? null;
            
            if ($block_index !== null && isset($current_blocks[$block_index])) {
                if ($new_content !== null) {
                    // Update block content
                    if (is_array($new_content)) {
                        $current_blocks[$block_index]['attrs'] = array_merge(
                            $current_blocks[$block_index]['attrs'] ?? array(),
                            $new_content
                        );
                    } else {
                        $current_blocks[$block_index]['innerContent'] = array($new_content);
                        $current_blocks[$block_index]['innerHTML'] = $new_content;
                    }
                }
            } elseif ($block_name) {
                // Find block by name and update
                foreach ($current_blocks as $index => $block) {
                    if ($block['blockName'] === $block_name) {
                        if (is_array($new_content)) {
                            $current_blocks[$index]['attrs'] = array_merge(
                                $current_blocks[$index]['attrs'] ?? array(),
                                $new_content
                            );
                        } else {
                            $current_blocks[$index]['innerContent'] = array($new_content);
                            $current_blocks[$index]['innerHTML'] = $new_content;
                        }
                        break;
                    }
                }
            }
        }
        
        return $current_blocks;
    }
}

