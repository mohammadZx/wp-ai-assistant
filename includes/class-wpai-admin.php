<?php
/**
 * Admin interface for WP AI Assistant
 */

if (!defined('ABSPATH')) {
    exit;
}

class WPAI_Admin {
    
    private $plugin;
    
    public function __construct($plugin = null) {
        // Require plugin instance to be passed to avoid circular dependency
        if ($plugin) {
            $this->plugin = $plugin;
        } else {
            // Fallback only if plugin is already initialized (should not happen in normal flow)
            $this->plugin = WPAI_Plugin::get_instance();
        }
        $this->init_hooks();
    }
    
    /**
     * Initialize hooks
     */
    private function init_hooks() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('add_meta_boxes', array($this, 'add_meta_boxes'));
        add_action('admin_footer', array($this, 'add_chat_modal'));
        add_action('wp_ajax_wpai_test_connection', array($this, 'ajax_test_connection'));
    }
    
    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_menu_page(
            __('WP AI Assistant', 'wpai-assistant'),
            __('AI Assistant', 'wpai-assistant'),
            'manage_options',
            'wpai-assistant',
            array($this, 'render_main_page'),
            'dashicons-robot',
            30
        );
        
        add_submenu_page(
            'wpai-assistant',
            __('Settings', 'wpai-assistant'),
            __('Settings', 'wpai-assistant'),
            'manage_options',
            'wpai-assistant-settings',
            array($this, 'render_settings_page')
        );
        
        add_submenu_page(
            'wpai-assistant',
            __('Chat', 'wpai-assistant'),
            __('Chat', 'wpai-assistant'),
            'edit_posts',
            'wpai-assistant-chat',
            array($this, 'render_chat_page')
        );
        
        add_submenu_page(
            'wpai-assistant',
            __('Topics', 'wpai-assistant'),
            __('Topics', 'wpai-assistant'),
            'edit_posts',
            'wpai-assistant-topics',
            array($this, 'render_topics_page')
        );
        
        add_submenu_page(
            'wpai-assistant',
            __('Crawler', 'wpai-assistant'),
            __('Crawler', 'wpai-assistant'),
            'edit_posts',
            'wpai-assistant-crawler',
            array($this, 'render_crawler_page')
        );
        
        add_submenu_page(
            'wpai-assistant',
            __('Audit Log', 'wpai-assistant'),
            __('Audit Log', 'wpai-assistant'),
            'manage_options',
            'wpai-assistant-audit',
            array($this, 'render_audit_page')
        );
    }
    
    /**
     * Register settings
     */
    public function register_settings() {
        // API Settings
        register_setting('wpai_settings', 'wpai_api_provider');
        register_setting('wpai_settings', 'wpai_api_key');
        register_setting('wpai_settings', 'wpai_mirror_link');
        register_setting('wpai_settings', 'wpai_default_model');
        
        // Model Settings
        register_setting('wpai_settings', 'wpai_default_temperature');
        register_setting('wpai_settings', 'wpai_default_top_p');
        register_setting('wpai_settings', 'wpai_default_max_tokens');
        register_setting('wpai_settings', 'wpai_default_frequency_penalty');
        register_setting('wpai_settings', 'wpai_default_presence_penalty');
        
        // Security Settings
        register_setting('wpai_settings', 'wpai_require_approval');
        register_setting('wpai_settings', 'wpai_auto_backup');
        register_setting('wpai_settings', 'wpai_dry_run_mode');
    }
    
    /**
     * Render main page
     */
    public function render_main_page() {
        include WPAI_PLUGIN_DIR . 'admin/views/main-page.php';
    }
    
    /**
     * Render settings page
     */
    public function render_settings_page() {
        include WPAI_PLUGIN_DIR . 'admin/views/settings-page.php';
    }
    
    /**
     * Render chat page
     */
    public function render_chat_page() {
        include WPAI_PLUGIN_DIR . 'admin/views/chat-page.php';
    }
    
    /**
     * Render topics page
     */
    public function render_topics_page() {
        include WPAI_PLUGIN_DIR . 'admin/views/topics-page.php';
    }
    
    /**
     * Render crawler page
     */
    public function render_crawler_page() {
        include WPAI_PLUGIN_DIR . 'admin/views/crawler-page.php';
    }
    
    /**
     * Render audit log page
     */
    public function render_audit_page() {
        include WPAI_PLUGIN_DIR . 'admin/views/audit-page.php';
    }
    
    /**
     * Add meta boxes to post/page editor
     */
    public function add_meta_boxes() {
        $post_types = get_post_types(array('public' => true));
        foreach ($post_types as $post_type) {
            add_meta_box(
                'wpai_assistant',
                __('AI Assistant', 'wpai-assistant'),
                array($this, 'render_meta_box'),
                $post_type,
                'side',
                'high'
            );
        }
    }
    
    /**
     * Render meta box
     */
    public function render_meta_box($post) {
        include WPAI_PLUGIN_DIR . 'admin/views/meta-box.php';
    }
    
    /**
     * Add chat modal to footer
     */
    public function add_chat_modal() {
        $screen = get_current_screen();
        if ($screen && ($screen->id === 'post' || $screen->id === 'page' || $screen->post_type)) {
            include WPAI_PLUGIN_DIR . 'admin/views/chat-modal.php';
        }
    }
    
    /**
     * AJAX: Test API connection
     */
    public function ajax_test_connection() {
        check_ajax_referer('wpai_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied', 'wpai-assistant')));
        }
        
        // Use plugin instance that was passed in constructor
        $api_manager = $this->plugin->api;
        if (!$api_manager) {
            wp_send_json_error(array('message' => __('API manager not available', 'wpai-assistant')));
        }
        
        $result = $api_manager->test_connection();
        
        // Return full result including response data
        if (isset($result['success']) && !$result['success']) {
            wp_send_json_error($result);
        } else {
            wp_send_json_success($result);
        }
    }
}

