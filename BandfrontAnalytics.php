<?php
/**
 * Plugin Name: Bandfront Analytics
 * Plugin URI: https://bandfront.com/analytics
 * Description: Lightweight analytics plugin for WordPress with music play tracking
 * Version: 1.0.0
 * Author: Bandfront
 * Text Domain: bandfront-analytics
 * Domain Path: /languages
 * License: GPL v2 or later
 * Requires at least: 5.8
 * Requires PHP: 7.4
 */

namespace bfa;

if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('BFA_VERSION', '1.0.0');
define('BFA_PLUGIN_PATH', __FILE__);
define('BFA_PLUGIN_URL', plugin_dir_url(__FILE__));
define('BFA_PLUGIN_DIR', plugin_dir_path(__FILE__));

// Autoload classes
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

// Initialize the plugin
function init() {
    $GLOBALS['BandfrontAnalytics'] = new Plugin();
}

add_action('plugins_loaded', __NAMESPACE__ . '\\init');

// Activation hook
register_activation_hook(__FILE__, function() {
    require_once BFA_PLUGIN_DIR . 'src/Database.php';
    Database::createTables();
});

// Deactivation hook
register_deactivation_hook(__FILE__, function() {
    wp_clear_scheduled_hook('bfa_hourly_aggregation');
    wp_clear_scheduled_hook('bfa_daily_cleanup');
});
