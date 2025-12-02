<?php
/**
 * Plugin Name: WP AI Assistant
 * Plugin URI: https://aryatehran.com
 * Description: AI-powered content generation and management assistant for WordPress
 * Version: 1.0.0
 * Author: Mohammad Yazdani
 * Author URI: https://aryatehran.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: wpai-assistant
 * Domain Path: /languages
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

// Define plugin constants
define('WPAI_VERSION', '1.0.0');
define('WPAI_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('WPAI_PLUGIN_URL', plugin_dir_url(__FILE__));
define('WPAI_PLUGIN_BASENAME', plugin_basename(__FILE__));

/**
 * The code that runs during plugin activation.
 */
function activate_wpai_assistant() {
    require_once WPAI_PLUGIN_DIR . 'includes/class-wpai-activator.php';
    WPAI_Activator::activate();
}

/**
 * The code that runs during plugin deactivation.
 */
function deactivate_wpai_assistant() {
    require_once WPAI_PLUGIN_DIR . 'includes/class-wpai-activator.php';
    WPAI_Activator::deactivate();
}

register_activation_hook(__FILE__, 'activate_wpai_assistant');
register_deactivation_hook(__FILE__, 'deactivate_wpai_assistant');

/**
 * Begins execution of the plugin.
 * 
 * Initialize the plugin on 'init' hook to ensure WordPress is fully loaded
 * and translations are available. This prevents the "translation loading too early" error.
 */
function run_wpai_assistant() {
    require_once WPAI_PLUGIN_DIR . 'includes/class-wpai-plugin.php';
    WPAI_Plugin::get_instance();
}

// Initialize plugin on 'init' hook with priority 10 (default)
// This ensures WordPress core is fully loaded and translations are available
// Priority 10 is standard and prevents "translation loading too early" errors
add_action('init', 'run_wpai_assistant', 10);
