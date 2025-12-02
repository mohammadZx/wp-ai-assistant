<?php
/**
 * Autoloader for WP AI Assistant
 */

if (!defined('ABSPATH')) {
    exit;
}

class WPAI_Autoloader {
    
    /**
     * Register autoloader
     */
    public static function register() {
        spl_autoload_register(array(__CLASS__, 'autoload'));
    }
    
    /**
     * Autoload classes
     */
    public static function autoload($class) {
        if (strpos($class, 'WPAI_') !== 0) {
            return;
        }
        
        $class = str_replace('WPAI_', '', $class);
        $class = str_replace('_', '-', $class);
        $class = strtolower($class);
        
        $file = WPAI_PLUGIN_DIR . 'includes/class-' . $class . '.php';
        
        if (file_exists($file)) {
            require_once $file;
        }
    }
}


