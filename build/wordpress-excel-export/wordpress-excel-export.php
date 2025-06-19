<?php
/**
 * Plugin Name: WordPress Excel Export
 * Plugin URI: https://github.com/your-username/wordpress-excel-export
 * Description: Export WordPress data to Excel with customizable templates and filters
 * Version: 1.0.1
 * Author: GradeIT
 * Author URI: https://gradeit.co.za
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: wordpress-excel-export
 * Domain Path: /languages
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('WEE_PLUGIN_URL', plugins_url('/', __FILE__));
define('WEE_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('WEE_PLUGIN_VERSION', '1.0.1');

// Include required files
require_once WEE_PLUGIN_PATH . 'includes/class-wee-plugin.php';
require_once WEE_PLUGIN_PATH . 'includes/class-wee-export.php';
require_once WEE_PLUGIN_PATH . 'includes/class-wee-templates.php';

// Initialize the plugin
function wee_init() {
    new WEE_Plugin();
}
add_action('plugins_loaded', 'wee_init');

// Periodic database optimization check
add_action('wp_loaded', array('WEE_Plugin', 'maybe_optimize_database'));

// Activation hook
register_activation_hook(__FILE__, 'wee_activate');
function wee_activate() {
    // Create database tables and optimize indexes
    WEE_Plugin::activate();
    
    // Set default options
    add_option('wee_version', WEE_PLUGIN_VERSION);
    
    // Clear any cached URLs
    delete_transient('wee_plugin_url');
}

// Deactivation hook
register_deactivation_hook(__FILE__, 'wee_deactivate');
function wee_deactivate() {
    // Cleanup
    delete_transient('wee_plugin_url');
} 