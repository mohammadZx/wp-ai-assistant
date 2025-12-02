<?php
/**
 * Elementor Editor Integration
 */

if (!defined('ABSPATH')) {
    exit;
}

class WPAI_Elementor_Integration {
    
    private $plugin;
    
    public function __construct($plugin = null) {
        $this->plugin = $plugin ? $plugin : WPAI_Plugin::get_instance();
        $this->init_hooks();
    }
    
    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // Check if Elementor is active
        add_action('plugins_loaded', array($this, 'check_elementor'));
    }
    
    /**
     * Check if Elementor is loaded and initialize integration
     */
    public function check_elementor() {
        // Check if Elementor plugin is active
        if (!did_action('elementor/loaded')) {
            // Try to check if Elementor class exists
            if (!class_exists('\Elementor\Plugin')) {
                return;
            }
        }
        
        // Register Elementor controls
        add_action('elementor/controls/register', array($this, 'register_controls'));
        
        // Add panel tab - use multiple hooks to ensure it loads
        add_action('elementor/editor/before_enqueue_scripts', array($this, 'enqueue_editor_scripts'));
        add_action('elementor/editor/after_enqueue_scripts', array($this, 'enqueue_editor_scripts'));
        add_action('elementor/editor/after_enqueue_styles', array($this, 'enqueue_editor_styles'));
        
        // Also try on admin init for editor pages
        add_action('admin_enqueue_scripts', array($this, 'maybe_enqueue_editor_scripts'), 20);
        
        // AJAX handlers
        add_action('wp_ajax_wpai_elementor_apply', array($this, 'ajax_apply_to_elementor'));
        add_action('wp_ajax_wpai_elementor_get_structure', array($this, 'ajax_get_elementor_structure'));
        add_action('wp_ajax_wpai_elementor_update_section', array($this, 'ajax_update_section'));
        add_action('wp_ajax_wpai_elementor_update_widget', array($this, 'ajax_update_widget'));
        add_action('wp_ajax_wpai_elementor_update_image', array($this, 'ajax_update_image'));
    }
    
    /**
     * Maybe enqueue editor scripts on admin pages
     */
    public function maybe_enqueue_editor_scripts($hook) {
        // Only on post edit pages
        if (!in_array($hook, array('post.php', 'post-new.php'))) {
            return;
        }
        
        $post_id = get_the_ID();
        if (!$post_id) {
            return;
        }
        
        // Check if this is an Elementor page
        if (get_post_meta($post_id, '_elementor_edit_mode', true) === 'builder' || 
            (isset($_GET['action']) && $_GET['action'] === 'elementor')) {
            $this->enqueue_editor_scripts();
            $this->enqueue_editor_styles();
        }
    }
    
    /**
     * Enqueue Elementor editor scripts
     */
    public function enqueue_editor_scripts() {
        $post_id = get_the_ID();
        if (!$post_id) {
            return;
        }
        
        // Check if Elementor is being used - also check URL parameter
        $is_elementor_editor = (
            get_post_meta($post_id, '_elementor_edit_mode', true) === 'builder' ||
            (isset($_GET['action']) && $_GET['action'] === 'elementor') ||
            (isset($_GET['elementor-preview']) && $_GET['elementor-preview'])
        );
        
        if (!$is_elementor_editor) {
            return;
        }
        
        wp_enqueue_script(
            'wpai-elementor-panel',
            WPAI_PLUGIN_URL . 'assets/js/elementor-panel.js',
            array('jquery'),
            WPAI_VERSION,
            true
        );
        
        wp_localize_script('wpai-elementor-panel', 'wpaiElementor', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('wpai_nonce'),
            'postId' => $post_id,
            'strings' => array(
                'title' => __('AI Assistant', 'wpai-assistant'),
                'promptPlaceholder' => __('Describe what you want to change (e.g., "Change the heading in section 1 to..." or "Update the image in the hero section...")', 'wpai-assistant'),
                'generating' => __('Processing your request...', 'wpai-assistant'),
                'applying' => __('Applying changes...', 'wpai-assistant'),
                'success' => __('Changes applied successfully!', 'wpai-assistant'),
                'error' => __('An error occurred', 'wpai-assistant'),
                'selectSection' => __('Select a section or widget first', 'wpai-assistant'),
            )
        ));
    }
    
    /**
     * Enqueue Elementor editor styles
     */
    public function enqueue_editor_styles() {
        $post_id = get_the_ID();
        if (!$post_id) {
            return;
        }
        
        // Check if Elementor is being used - also check URL parameter
        $is_elementor_editor = (
            get_post_meta($post_id, '_elementor_edit_mode', true) === 'builder' ||
            (isset($_GET['action']) && $_GET['action'] === 'elementor') ||
            (isset($_GET['elementor-preview']) && $_GET['elementor-preview'])
        );
        
        if (!$is_elementor_editor) {
            return;
        }
        
        wp_enqueue_style(
            'wpai-elementor-panel',
            WPAI_PLUGIN_URL . 'assets/css/elementor-panel.css',
            array(),
            WPAI_VERSION
        );
    }
    
    /**
     * Register Elementor controls (if needed)
     */
    public function register_controls($controls_manager) {
        // Can add custom controls here if needed
    }
    
    /**
     * AJAX: Get Elementor structure
     */
    public function ajax_get_elementor_structure() {
        check_ajax_referer('wpai_nonce', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(array('message' => __('Permission denied', 'wpai-assistant')));
        }
        
        $post_id = intval($_POST['post_id'] ?? 0);
        if (!$post_id) {
            wp_send_json_error(array('message' => __('Post ID is required', 'wpai-assistant')));
        }
        
        $elementor_data = get_post_meta($post_id, '_elementor_data', true);
        if (empty($elementor_data)) {
            wp_send_json_error(array('message' => __('No Elementor data found', 'wpai-assistant')));
        }
        
        $data = json_decode($elementor_data, true);
        
        wp_send_json_success(array(
            'structure' => $data,
            'post_id' => $post_id,
        ));
    }
    
    /**
     * AJAX: Apply changes to Elementor
     */
    public function ajax_apply_to_elementor() {
        check_ajax_referer('wpai_nonce', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(array('message' => __('Permission denied', 'wpai-assistant')));
        }
        
        $post_id = intval($_POST['post_id'] ?? 0);
        $elementor_data = stripslashes($_POST['elementor_data'] ?? '');
        
        if (!$post_id || empty($elementor_data)) {
            wp_send_json_error(array('message' => __('Post ID and Elementor data are required', 'wpai-assistant')));
        }
        
        // Validate JSON
        $data = json_decode($elementor_data, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            wp_send_json_error(array('message' => __('Invalid JSON data', 'wpai-assistant')));
        }
        
        // Update Elementor data
        update_post_meta($post_id, '_elementor_data', wp_slash($elementor_data));
        
        // Clear Elementor cache
        if (class_exists('\Elementor\Plugin')) {
            \Elementor\Plugin::$instance->files_manager->clear_cache();
        }
        
        wp_send_json_success(array(
            'message' => __('Changes applied successfully', 'wpai-assistant'),
            'post_id' => $post_id,
        ));
    }
    
    /**
     * AJAX: Update specific section
     */
    public function ajax_update_section() {
        check_ajax_referer('wpai_nonce', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(array('message' => __('Permission denied', 'wpai-assistant')));
        }
        
        $post_id = intval($_POST['post_id'] ?? 0);
        $section_id = sanitize_text_field($_POST['section_id'] ?? '');
        $section_data = json_decode(stripslashes($_POST['section_data'] ?? '{}'), true);
        
        if (!$post_id || empty($section_id)) {
            wp_send_json_error(array('message' => __('Post ID and section ID are required', 'wpai-assistant')));
        }
        
        $elementor_data = get_post_meta($post_id, '_elementor_data', true);
        if (empty($elementor_data)) {
            wp_send_json_error(array('message' => __('No Elementor data found', 'wpai-assistant')));
        }
        
        $data = json_decode($elementor_data, true);
        
        // Ensure container-based structure
        if (!isset($data['content']) && is_array($data)) {
            $data = array('content' => $data, 'version' => '0.4');
        }
        
        // Find and update container
        $updated = $this->update_elementor_section($data, $section_id, $section_data);
        
        if (!$updated) {
            wp_send_json_error(array('message' => __('Section not found', 'wpai-assistant')));
        }
        
        // Save updated data
        update_post_meta($post_id, '_elementor_data', wp_slash(json_encode($data)));
        
        // Clear cache
        if (class_exists('\Elementor\Plugin')) {
            \Elementor\Plugin::$instance->files_manager->clear_cache();
        }
        
        wp_send_json_success(array(
            'message' => __('Section updated successfully', 'wpai-assistant'),
        ));
    }
    
    /**
     * AJAX: Update specific widget
     */
    public function ajax_update_widget() {
        check_ajax_referer('wpai_nonce', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(array('message' => __('Permission denied', 'wpai-assistant')));
        }
        
        $post_id = intval($_POST['post_id'] ?? 0);
        $widget_id = sanitize_text_field($_POST['widget_id'] ?? '');
        $widget_data = json_decode(stripslashes($_POST['widget_data'] ?? '{}'), true);
        
        if (!$post_id || empty($widget_id)) {
            wp_send_json_error(array('message' => __('Post ID and widget ID are required', 'wpai-assistant')));
        }
        
        $elementor_data = get_post_meta($post_id, '_elementor_data', true);
        if (empty($elementor_data)) {
            wp_send_json_error(array('message' => __('No Elementor data found', 'wpai-assistant')));
        }
        
        $data = json_decode($elementor_data, true);
        
        // Ensure container-based structure
        if (!isset($data['content']) && is_array($data)) {
            $data = array('content' => $data, 'version' => '0.4');
        }
        
        // Find and update widget
        $updated = $this->update_elementor_widget($data, $widget_id, $widget_data);
        
        if (!$updated) {
            wp_send_json_error(array('message' => __('Widget not found', 'wpai-assistant')));
        }
        
        // Save updated data
        update_post_meta($post_id, '_elementor_data', wp_slash(json_encode($data)));
        
        // Clear cache
        if (class_exists('\Elementor\Plugin')) {
            \Elementor\Plugin::$instance->files_manager->clear_cache();
        }
        
        wp_send_json_success(array(
            'message' => __('Widget updated successfully', 'wpai-assistant'),
        ));
    }
    
    /**
     * AJAX: Update image in Elementor
     */
    public function ajax_update_image() {
        check_ajax_referer('wpai_nonce', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(array('message' => __('Permission denied', 'wpai-assistant')));
        }
        
        $post_id = intval($_POST['post_id'] ?? 0);
        $image_id = sanitize_text_field($_POST['image_id'] ?? '');
        $image_url = esc_url_raw($_POST['image_url'] ?? '');
        $image_attachment_id = intval($_POST['image_attachment_id'] ?? 0);
        
        if (!$post_id || empty($image_id)) {
            wp_send_json_error(array('message' => __('Post ID and image ID are required', 'wpai-assistant')));
        }
        
        if (empty($image_url) && !$image_attachment_id) {
            wp_send_json_error(array('message' => __('Image URL or attachment ID is required', 'wpai-assistant')));
        }
        
        $elementor_data = get_post_meta($post_id, '_elementor_data', true);
        if (empty($elementor_data)) {
            wp_send_json_error(array('message' => __('No Elementor data found', 'wpai-assistant')));
        }
        
        $data = json_decode($elementor_data, true);
        
        // Ensure container-based structure
        if (!isset($data['content']) && is_array($data)) {
            $data = array('content' => $data, 'version' => '0.4');
        }
        
        // If URL provided, download and create attachment
        if (!empty($image_url) && !$image_attachment_id) {
            require_once(ABSPATH . 'wp-admin/includes/file.php');
            require_once(ABSPATH . 'wp-admin/includes/media.php');
            require_once(ABSPATH . 'wp-admin/includes/image.php');
            
            $tmp = download_url($image_url);
            if (is_wp_error($tmp)) {
                wp_send_json_error(array('message' => $tmp->get_error_message()));
            }
            
            $file_array = array(
                'name' => basename($image_url),
                'tmp_name' => $tmp
            );
            
            $attachment_id = media_handle_sideload($file_array, $post_id);
            if (is_wp_error($attachment_id)) {
                @unlink($tmp);
                wp_send_json_error(array('message' => $attachment_id->get_error_message()));
            }
            
            $image_attachment_id = $attachment_id;
        }
        
        // Find and update image
        $updated = $this->update_elementor_image($data, $image_id, $image_attachment_id);
        
        if (!$updated) {
            wp_send_json_error(array('message' => __('Image not found', 'wpai-assistant')));
        }
        
        // Save updated data
        update_post_meta($post_id, '_elementor_data', wp_slash(json_encode($data)));
        
        // Clear cache
        if (class_exists('\Elementor\Plugin')) {
            \Elementor\Plugin::$instance->files_manager->clear_cache();
        }
        
        wp_send_json_success(array(
            'message' => __('Image updated successfully', 'wpai-assistant'),
            'attachment_id' => $image_attachment_id,
        ));
    }
    
    /**
     * Update Elementor container (container-based structure)
     */
    private function update_elementor_section(&$data, $container_id, $container_data) {
        // Handle container-based structure
        if (isset($data['content']) && is_array($data['content'])) {
            return $this->update_elementor_container_recursive($data['content'], $container_id, $container_data);
        }
        
        // Fallback for old structure
        foreach ($data as &$item) {
            if (isset($item['id']) && $item['id'] === $container_id) {
                if (isset($item['settings'])) {
                    $item['settings'] = array_merge($item['settings'], $container_data);
                } else {
                    $item = array_merge($item, $container_data);
                }
                return true;
            }
            
            if (isset($item['elements'])) {
                if ($this->update_elementor_container_recursive($item['elements'], $container_id, $container_data)) {
                    return true;
                }
            }
        }
        
        return false;
    }
    
    /**
     * Update Elementor widget (container-based structure)
     */
    private function update_elementor_widget(&$data, $widget_id, $widget_data) {
        // Handle container-based structure
        if (isset($data['content']) && is_array($data['content'])) {
            return $this->update_elementor_widget_recursive($data['content'], $widget_id, $widget_data);
        }
        
        // Fallback for old structure
        return $this->update_elementor_widget_recursive($data, $widget_id, $widget_data);
    }
    
    /**
     * Update Elementor image (container-based structure)
     */
    private function update_elementor_image(&$data, $image_id, $attachment_id) {
        // Handle container-based structure
        if (isset($data['content']) && is_array($data['content'])) {
            return $this->update_elementor_image_recursive($data['content'], $image_id, $attachment_id);
        }
        
        // Fallback for old structure
        return $this->update_elementor_image_recursive($data, $image_id, $attachment_id);
    }
    
    /**
     * Recursively update container in container-based structure
     */
    private function update_elementor_container_recursive(&$elements, $container_id, $container_data) {
        foreach ($elements as &$element) {
            // Check if this is the container we're looking for
            if (isset($element['id']) && $element['id'] === $container_id && 
                isset($element['elType']) && $element['elType'] === 'container') {
                if (isset($element['settings'])) {
                    $element['settings'] = array_merge($element['settings'], $container_data);
                } else {
                    $element = array_merge($element, $container_data);
                }
                return true;
            }
            
            // Recursively check nested elements
            if (isset($element['elements']) && is_array($element['elements'])) {
                if ($this->update_elementor_container_recursive($element['elements'], $container_id, $container_data)) {
                    return true;
                }
            }
        }
        
        return false;
    }
    
    /**
     * Recursively update widget in container-based structure
     */
    private function update_elementor_widget_recursive(&$elements, $widget_id, $widget_data) {
        foreach ($elements as &$element) {
            // Check if this is the widget we're looking for
            if (isset($element['id']) && $element['id'] === $widget_id && 
                isset($element['elType']) && $element['elType'] === 'widget') {
                if (isset($element['settings'])) {
                    $element['settings'] = array_merge($element['settings'], $widget_data);
                } else {
                    $element['settings'] = $widget_data;
                }
                return true;
            }
            
            // Recursively check nested elements (for nested widgets)
            if (isset($element['elements']) && is_array($element['elements'])) {
                if ($this->update_elementor_widget_recursive($element['elements'], $widget_id, $widget_data)) {
                    return true;
                }
            }
        }
        
        return false;
    }
    
    /**
     * Recursively update image in container-based structure
     */
    private function update_elementor_image_recursive(&$elements, $image_id, $attachment_id) {
        foreach ($elements as &$element) {
            // Check if this is an image widget
            if (isset($element['id']) && $element['id'] === $image_id) {
                $widget_type = $element['widgetType'] ?? '';
                
                // Handle image widget
                if ($widget_type === 'image' || $widget_type === 'image-box') {
                    if (!isset($element['settings'])) {
                        $element['settings'] = array();
                    }
                    $element['settings']['image'] = array(
                        'id' => $attachment_id,
                        'url' => wp_get_attachment_image_url($attachment_id, 'full'),
                    );
                    return true;
                }
            }
            
            // Recursively check nested elements
            if (isset($element['elements']) && is_array($element['elements'])) {
                if ($this->update_elementor_image_recursive($element['elements'], $image_id, $attachment_id)) {
                    return true;
                }
            }
        }
        
        return false;
    }
}

