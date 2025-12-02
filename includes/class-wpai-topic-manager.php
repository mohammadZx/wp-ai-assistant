<?php
/**
 * Topic Manager for managing topics and initial data
 */

if (!defined('ABSPATH')) {
    exit;
}

class WPAI_Topic_Manager {
    
    private $table_name;
    
    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'wpai_topics';
        $this->init_hooks();
    }
    
    /**
     * Initialize hooks
     */
    private function init_hooks() {
        add_action('wp_ajax_wpai_create_topic', array($this, 'ajax_create_topic'));
        add_action('wp_ajax_wpai_update_topic', array($this, 'ajax_update_topic'));
        add_action('wp_ajax_wpai_delete_topic', array($this, 'ajax_delete_topic'));
        add_action('wp_ajax_wpai_get_topic', array($this, 'ajax_get_topic'));
        add_action('wp_ajax_wpai_list_topics', array($this, 'ajax_list_topics'));
    }
    
    /**
     * Create topic
     */
    public function create_topic($name, $description = '', $initial_data = '', $settings = array()) {
        global $wpdb;
        
        $user_id = get_current_user_id();
        
        $result = $wpdb->insert(
            $this->table_name,
            array(
                'name' => sanitize_text_field($name),
                'description' => sanitize_textarea_field($description),
                'initial_data' => wp_kses_post($initial_data),
                'settings' => json_encode($settings),
                'created_by' => $user_id,
                'created_at' => current_time('mysql'),
            ),
            array('%s', '%s', '%s', '%s', '%d', '%s')
        );
        
        if ($result === false) {
            return new WP_Error('db_error', __('Failed to create topic', 'wpai-assistant'));
        }
        
        return $wpdb->insert_id;
    }
    
    /**
     * Update topic
     */
    public function update_topic($topic_id, $data) {
        global $wpdb;
        
        $user_id = get_current_user_id();
        
        // Check ownership
        $topic = $this->get_topic($topic_id);
        if (!$topic || $topic->created_by != $user_id && !current_user_can('manage_options')) {
            return new WP_Error('permission_denied', __('Permission denied', 'wpai-assistant'));
        }
        
        $update_data = array();
        $format = array();
        
        if (isset($data['name'])) {
            $update_data['name'] = sanitize_text_field($data['name']);
            $format[] = '%s';
        }
        
        if (isset($data['description'])) {
            $update_data['description'] = sanitize_textarea_field($data['description']);
            $format[] = '%s';
        }
        
        if (isset($data['initial_data'])) {
            $update_data['initial_data'] = wp_kses_post($data['initial_data']);
            $format[] = '%s';
        }
        
        if (isset($data['settings'])) {
            $update_data['settings'] = json_encode($data['settings']);
            $format[] = '%s';
        }
        
        if (empty($update_data)) {
            return new WP_Error('no_data', __('No data to update', 'wpai-assistant'));
        }
        
        $result = $wpdb->update(
            $this->table_name,
            $update_data,
            array('id' => $topic_id),
            $format,
            array('%d')
        );
        
        if ($result === false) {
            return new WP_Error('db_error', __('Failed to update topic', 'wpai-assistant'));
        }
        
        return true;
    }
    
    /**
     * Delete topic
     */
    public function delete_topic($topic_id) {
        global $wpdb;
        
        $user_id = get_current_user_id();
        
        // Check ownership
        $topic = $this->get_topic($topic_id);
        if (!$topic || $topic->created_by != $user_id && !current_user_can('manage_options')) {
            return new WP_Error('permission_denied', __('Permission denied', 'wpai-assistant'));
        }
        
        $result = $wpdb->delete(
            $this->table_name,
            array('id' => $topic_id),
            array('%d')
        );
        
        if ($result === false) {
            return new WP_Error('db_error', __('Failed to delete topic', 'wpai-assistant'));
        }
        
        return true;
    }
    
    /**
     * Get topic
     */
    public function get_topic($topic_id) {
        global $wpdb;
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table_name} WHERE id = %d",
            $topic_id
        ));
    }
    
    /**
     * List topics
     */
    public function list_topics($user_id = null, $limit = 50) {
        global $wpdb;
        
        if (!$user_id) {
            $user_id = get_current_user_id();
        }
        
        $query = "SELECT * FROM {$this->table_name}";
        $where = array();
        $params = array();
        
        if (!current_user_can('manage_options')) {
            $where[] = "created_by = %d";
            $params[] = $user_id;
        }
        
        if (!empty($where)) {
            $query .= " WHERE " . implode(" AND ", $where);
        }
        
        $query .= " ORDER BY created_at DESC LIMIT %d";
        $params[] = $limit;
        
        if (!empty($params)) {
            return $wpdb->get_results($wpdb->prepare($query, ...$params));
        }
        
        return $wpdb->get_results($query);
    }
    
    /**
     * Get initial data for topic
     */
    public function get_initial_data($topic_id) {
        $topic = $this->get_topic($topic_id);
        
        if (!$topic) {
            return '';
        }
        
        return $topic->initial_data;
    }
    
    /**
     * AJAX: Create topic
     */
    public function ajax_create_topic() {
        check_ajax_referer('wpai_nonce', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(array('message' => __('Permission denied', 'wpai-assistant')));
        }
        
        $name = sanitize_text_field($_POST['name'] ?? '');
        $description = sanitize_textarea_field($_POST['description'] ?? '');
        $initial_data = wp_kses_post($_POST['initial_data'] ?? '');
        $settings = json_decode(stripslashes($_POST['settings'] ?? '{}'), true);
        
        if (empty($name)) {
            wp_send_json_error(array('message' => __('Topic name is required', 'wpai-assistant')));
        }
        
        $topic_id = $this->create_topic($name, $description, $initial_data, $settings);
        
        if (is_wp_error($topic_id)) {
            wp_send_json_error(array('message' => $topic_id->get_error_message()));
        }
        
        wp_send_json_success(array('topic_id' => $topic_id));
    }
    
    /**
     * AJAX: Update topic
     */
    public function ajax_update_topic() {
        check_ajax_referer('wpai_nonce', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(array('message' => __('Permission denied', 'wpai-assistant')));
        }
        
        $topic_id = intval($_POST['topic_id'] ?? 0);
        $data = array();
        
        if (isset($_POST['name'])) {
            $data['name'] = sanitize_text_field($_POST['name']);
        }
        if (isset($_POST['description'])) {
            $data['description'] = sanitize_textarea_field($_POST['description']);
        }
        if (isset($_POST['initial_data'])) {
            $data['initial_data'] = wp_kses_post($_POST['initial_data']);
        }
        if (isset($_POST['settings'])) {
            $data['settings'] = json_decode(stripslashes($_POST['settings']), true);
        }
        
        $result = $this->update_topic($topic_id, $data);
        
        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        }
        
        wp_send_json_success();
    }
    
    /**
     * AJAX: Delete topic
     */
    public function ajax_delete_topic() {
        check_ajax_referer('wpai_nonce', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(array('message' => __('Permission denied', 'wpai-assistant')));
        }
        
        $topic_id = intval($_POST['topic_id'] ?? 0);
        
        $result = $this->delete_topic($topic_id);
        
        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        }
        
        wp_send_json_success();
    }
    
    /**
     * AJAX: Get topic
     */
    public function ajax_get_topic() {
        check_ajax_referer('wpai_nonce', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(array('message' => __('Permission denied', 'wpai-assistant')));
        }
        
        $topic_id = intval($_POST['topic_id'] ?? 0);
        $topic = $this->get_topic($topic_id);
        
        if (!$topic) {
            wp_send_json_error(array('message' => __('Topic not found', 'wpai-assistant')));
        }
        
        wp_send_json_success(array('topic' => $topic));
    }
    
    /**
     * AJAX: List topics
     */
    public function ajax_list_topics() {
        check_ajax_referer('wpai_nonce', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(array('message' => __('Permission denied', 'wpai-assistant')));
        }
        
        $topics = $this->list_topics();
        
        wp_send_json_success(array('topics' => $topics));
    }
}

