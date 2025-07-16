<?php
/**
 * Plugin Name: Bandfront Analytics
 * Plugin URI: https://bandfront.com/analytics
 * Description: Privacy-focused analytics for music websites
 * Version: 1.0.0
 * Author: Bandfront
 * Author URI: https://bandfront.com
 * License: GPL v2 or later
 * Text Domain: bandfront-analytics
 * Domain Path: /languages
 * Requires PHP: 7.4
 * Requires at least: 5.8
 */

namespace bfa;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('BFA_VERSION', '1.0.0');
define('BFA_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('BFA_PLUGIN_URL', plugin_dir_url(__FILE__));
define('BFA_PLUGIN_FILE', __FILE__);

// Autoloader
spl_autoload_register(function ($class) {
    $prefix = 'bfa\\';
    $base_dir = __DIR__ . '/src/';
    
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }
    
    $relative_class = substr($class, $len);
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';
    
    if (file_exists($file)) {
        require $file;
    }
});

// Initialize plugin
function init() {
    global $bfa_plugin;
    $bfa_plugin = new Plugin(__FILE__);
}
add_action('plugins_loaded', __NAMESPACE__ . '\\init');

// Activation hook
register_activation_hook(__FILE__, function() {
    require_once __DIR__ . '/src/Database.php';
    $database = new Database();
    $database->createTables();
    
    // Set default options
    add_option('bfa_tracking_enabled', true);
    add_option('bfa_exclude_admins', true);
    add_option('bfa_respect_dnt', true);
    add_option('bfa_data_retention_days', 365);
});

// Deactivation hook
register_deactivation_hook(__FILE__, function() {
    // Clean up scheduled tasks
    wp_clear_scheduled_hook('bfa_cleanup_old_data');
});

// Uninstall hook
register_uninstall_hook(__FILE__, __NAMESPACE__ . '\\uninstall');

function uninstall() {
    // Only remove data if explicitly set
    if (get_option('bfa_delete_data_on_uninstall')) {
        global $wpdb;
        $tables = ['bfa_events', 'bfa_sessions', 'bfa_daily_stats'];
        foreach ($tables as $table) {
            $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}{$table}");
        }
        
        // Remove all options
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE 'bfa_%'");
    }
}

// Global accessor function
function bfa() {
    global $bfa_plugin;
    return $bfa_plugin;
}
