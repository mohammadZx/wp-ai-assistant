<?php
/**
 * Security and audit logging
 */

if (!defined('ABSPATH')) {
    exit;
}

class WPAI_Security {
    
    private $table_name;
    
    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'wpai_audit_log';
        $this->init_hooks();
    }
    
    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // Log actions automatically
        add_action('wpai_content_applied', array($this, 'log_content_application'), 10, 3);
        add_action('wpai_post_generated', array($this, 'log_post_generation'), 10, 2);
    }
    
    /**
     * Log action to audit log
     */
    public function log_action($action, $object_type = null, $object_id = null, $details = array()) {
        global $wpdb;
        
        $user_id = get_current_user_id();
        $ip_address = $this->get_client_ip();
        
        $wpdb->insert(
            $this->table_name,
            array(
                'user_id' => $user_id,
                'action' => sanitize_text_field($action),
                'object_type' => $object_type ? sanitize_text_field($object_type) : null,
                'object_id' => $object_id ? intval($object_id) : null,
                'details' => json_encode($details),
                'ip_address' => $ip_address,
                'created_at' => current_time('mysql'),
            ),
            array('%d', '%s', '%s', '%d', '%s', '%s', '%s')
        );
        
        return $wpdb->insert_id;
    }
    
    /**
     * Get audit log entries
     */
    public function get_audit_log($filters = array(), $limit = 100) {
        global $wpdb;
        
        $where = array('1=1');
        $params = array();
        
        if (!empty($filters['user_id'])) {
            $where[] = "user_id = %d";
            $params[] = intval($filters['user_id']);
        }
        
        if (!empty($filters['action'])) {
            $where[] = "action = %s";
            $params[] = sanitize_text_field($filters['action']);
        }
        
        if (!empty($filters['object_type'])) {
            $where[] = "object_type = %s";
            $params[] = sanitize_text_field($filters['object_type']);
        }
        
        if (!empty($filters['date_from'])) {
            $where[] = "created_at >= %s";
            $params[] = sanitize_text_field($filters['date_from']);
        }
        
        if (!empty($filters['date_to'])) {
            $where[] = "created_at <= %s";
            $params[] = sanitize_text_field($filters['date_to']);
        }
        
        $query = "SELECT * FROM {$this->table_name} WHERE " . implode(' AND ', $where);
        $query .= " ORDER BY created_at DESC LIMIT %d";
        $params[] = $limit;
        
        if (!empty($params)) {
            return $wpdb->get_results($wpdb->prepare($query, ...$params));
        }
        
        return $wpdb->get_results($query);
    }
    
    /**
     * Create backup of post/page
     */
    public function create_backup($post_id) {
        $post = get_post($post_id);
        
        if (!$post) {
            return new WP_Error('post_not_found', __('Post not found', 'wpai-assistant'));
        }
        
        // Create revision
        $revision_id = wp_save_post_revision($post_id);
        
        if (!$revision_id) {
            return new WP_Error('backup_failed', __('Failed to create backup', 'wpai-assistant'));
        }
        
        // Store backup metadata
        update_post_meta($revision_id, '_wpai_backup', true);
        update_post_meta($revision_id, '_wpai_backup_date', current_time('mysql'));
        update_post_meta($revision_id, '_wpai_backup_user', get_current_user_id());
        
        return $revision_id;
    }
    
    /**
     * Restore from backup
     */
    public function restore_backup($post_id, $revision_id) {
        $revision = get_post($revision_id);
        
        if (!$revision || $revision->post_parent != $post_id) {
            return new WP_Error('invalid_revision', __('Invalid revision', 'wpai-assistant'));
        }
        
        $post = get_post($post_id);
        
        if (!$post) {
            return new WP_Error('post_not_found', __('Post not found', 'wpai-assistant'));
        }
        
        // Restore content
        $update_data = array(
            'ID' => $post_id,
            'post_content' => $revision->post_content,
            'post_title' => $revision->post_title,
        );
        
        $result = wp_update_post($update_data, true);
        
        if (is_wp_error($result)) {
            return $result;
        }
        
        // Log restoration
        $this->log_action('restore_backup', 'post', $post_id, array(
            'revision_id' => $revision_id,
        ));
        
        return true;
    }
    
    /**
     * Get client IP address
     */
    private function get_client_ip() {
        $ip_keys = array(
            'HTTP_CF_CONNECTING_IP', // Cloudflare
            'HTTP_X_REAL_IP',
            'HTTP_X_FORWARDED_FOR',
            'REMOTE_ADDR',
        );
        
        foreach ($ip_keys as $key) {
            if (!empty($_SERVER[$key])) {
                $ip = $_SERVER[$key];
                // Handle comma-separated IPs (from proxies)
                if (strpos($ip, ',') !== false) {
                    $ip = trim(explode(',', $ip)[0]);
                }
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }
        
        return '0.0.0.0';
    }
    
    /**
     * Check if action requires approval
     */
    public function requires_approval($action) {
        $require_approval = get_option('wpai_require_approval', true);
        
        if (!$require_approval) {
            return false;
        }
        
        // List of actions that always require approval
        $critical_actions = array('delete', 'publish', 'apply_content');
        
        return in_array($action, $critical_actions);
    }
    
    /**
     * Verify nonce for action
     */
    public function verify_action($action, $nonce) {
        return wp_verify_nonce($nonce, 'wpai_' . $action);
    }
    
    /**
     * Log content application
     */
    public function log_content_application($post_id, $content, $dry_run) {
        if (!$dry_run) {
            $this->log_action('apply_content', 'post', $post_id, array(
                'content_length' => strlen($content),
                'dry_run' => false,
            ));
        }
    }
    
    /**
     * Log post generation
     */
    public function log_post_generation($post_id, $title) {
        $this->log_action('generate_post', 'post', $post_id, array(
            'title' => $title,
        ));
    }
}

