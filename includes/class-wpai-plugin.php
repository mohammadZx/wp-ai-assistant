<?php
/**
 * Main plugin class
 */

if (!defined('ABSPATH')) {
    exit;
}

class WPAI_Plugin {
    
    private static $instance = null;
    
    public $admin;
    public $api;
    public $chat;
    public $crawler;
    public $security;
    public $content_generator;
    public $file_handler;
    
    /**
     * Get singleton instance
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        $this->load_dependencies();
        $this->init_components();
        $this->init_hooks();
    }
    
    /**
     * Load dependencies
     */
    private function load_dependencies() {
        require_once WPAI_PLUGIN_DIR . 'includes/class-wpai-activator.php';
        require_once WPAI_PLUGIN_DIR . 'includes/class-wpai-admin.php';
        require_once WPAI_PLUGIN_DIR . 'includes/class-wpai-api-manager.php';
        require_once WPAI_PLUGIN_DIR . 'includes/class-wpai-chat.php';
        require_once WPAI_PLUGIN_DIR . 'includes/class-wpai-crawler.php';
        require_once WPAI_PLUGIN_DIR . 'includes/class-wpai-security.php';
        require_once WPAI_PLUGIN_DIR . 'includes/class-wpai-intent-detector.php';
        require_once WPAI_PLUGIN_DIR . 'includes/class-wpai-content-generator.php';
        require_once WPAI_PLUGIN_DIR . 'includes/class-wpai-file-handler.php';
        require_once WPAI_PLUGIN_DIR . 'includes/class-wpai-topic-manager.php';
    }
    
    /**
     * Initialize components
     */
    private function init_components() {
        // Initialize core components first (no dependencies)
        $this->api = new WPAI_API_Manager();
        $this->security = new WPAI_Security();
        $this->file_handler = new WPAI_File_Handler();
        
        // Initialize dependent components (pass dependencies to avoid circular reference)
        $this->content_generator = new WPAI_Content_Generator($this->api, $this->security);
        $this->admin = new WPAI_Admin($this);
        $this->chat = new WPAI_Chat($this->api);
        $this->crawler = new WPAI_Crawler($this->api, $this->content_generator);
    }
    
    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // Load text domain immediately since we're already on 'init' hook
        $this->load_textdomain();
        
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_scripts'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
    }
    
    /**
     * Load text domain
     */
    private function load_textdomain() {
        load_plugin_textdomain('wpai-assistant', false, dirname(WPAI_PLUGIN_BASENAME) . '/languages');
    }
    
    /**
     * Enqueue frontend scripts
     */
    public function enqueue_frontend_scripts() {
        // Frontend scripts if needed
    }
    
    /**
     * Enqueue admin scripts
     */
    public function enqueue_admin_scripts($hook) {
        wp_enqueue_style('wpai-admin-style', WPAI_PLUGIN_URL . 'assets/css/admin.css', array(), WPAI_VERSION);
        wp_enqueue_script('wpai-admin-script', WPAI_PLUGIN_URL . 'assets/js/admin.js', array('jquery'), WPAI_VERSION, true);
        
        wp_localize_script('wpai-admin-script', 'wpaiData', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('wpai_nonce'),
            'strings' => array(
                'confirmApply' => __('Are you sure you want to apply these changes?', 'wpai-assistant'),
                'error' => __('An error occurred. Please try again.', 'wpai-assistant'),
                'confirmClear' => __('Are you sure you want to clear this chat?', 'wpai-assistant'),
                'thinking' => __('Thinking...', 'wpai-assistant'),
            )
        ));
    }
}

