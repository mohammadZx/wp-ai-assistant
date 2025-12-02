<?php
/**
 * Plugin activation handler
 */

if (!defined('ABSPATH')) {
    exit;
}

class WPAI_Activator {
    
    /**
     * Activate plugin
     */
    public static function activate() {
        self::create_tables();
        self::set_default_options();
        flush_rewrite_rules();
    }
    
    /**
     * Deactivate plugin
     */
    public static function deactivate() {
        flush_rewrite_rules();
    }
    
    /**
     * Create database tables
     */
    private static function create_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // Chat history table
        $table_chats = $wpdb->prefix . 'wpai_chats';
        $sql_chats = "CREATE TABLE IF NOT EXISTS $table_chats (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id bigint(20) UNSIGNED NOT NULL,
            session_id varchar(255) NOT NULL,
            message text NOT NULL,
            response text,
            model varchar(100),
            settings text,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY session_id (session_id)
        ) $charset_collate;";
        
        // Topics table
        $table_topics = $wpdb->prefix . 'wpai_topics';
        $sql_topics = "CREATE TABLE IF NOT EXISTS $table_topics (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            description text,
            initial_data longtext,
            settings text,
            created_by bigint(20) UNSIGNED NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY created_by (created_by)
        ) $charset_collate;";
        
        // Audit log table
        $table_audit = $wpdb->prefix . 'wpai_audit_log';
        $sql_audit = "CREATE TABLE IF NOT EXISTS $table_audit (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id bigint(20) UNSIGNED NOT NULL,
            action varchar(100) NOT NULL,
            object_type varchar(50),
            object_id bigint(20) UNSIGNED,
            details text,
            ip_address varchar(45),
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY action (action),
            KEY created_at (created_at)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql_chats);
        dbDelta($sql_topics);
        dbDelta($sql_audit);
    }
    
    /**
     * Set default options
     */
    private static function set_default_options() {
        $defaults = array(
            'wpai_api_provider' => 'openai',
            'wpai_api_key' => '',
            'wpai_mirror_link' => '',
            'wpai_default_model' => 'gpt-3.5-turbo',
            'wpai_default_temperature' => 0.7,
            'wpai_default_top_p' => 1.0,
            'wpai_default_max_tokens' => 2000,
            'wpai_require_approval' => true,
            'wpai_auto_backup' => true,
        );
        
        foreach ($defaults as $key => $value) {
            if (get_option($key) === false) {
                add_option($key, $value);
            }
        }
    }
}


