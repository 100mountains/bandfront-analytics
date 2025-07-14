100mountains: Transform this existing WordPress plugin structure into a lightweight analytics plugin that tracks page views, music plays, and provides admin UI similar to Jetpack Stats but storing data locally. The namespace has to be changed to bfa maybe and BFA constants. we have to add the variables we need in config as well 

REQUIREMENTS:
1. Add an "eye" column to posts/pages admin showing view counts
2. Add a stats widget to WordPress admin bar showing a traffic graph and other stats like today's views
3. Track music play events via JavaScript
4. Store all data in custom database tables for performance
5. Use AJAX for real-time updates without page reloads
6. Implement data sampling for high-traffic sites
7. Add privacy-compliant tracking (no personal data)

TECHNICAL APPROACH:
- Use WordPress Transients API for caching
- Implement batch processing for database writes
- Create REST API endpoints for retrieving stats
- Use Chart.js for visualizations
- Add WP-Cron jobs for data cleanup/aggregation

The plugin should be performant enough to handle 100k+ daily pageviews without impacting site speed.
Variables Needed for Lean Analytics Plugin
Core Tracking Variables
php// Page/Post tracking
$post_id
$post_type
$view_timestamp
$referrer_domain
$user_agent_hash // Hashed for privacy
$session_id // Anonymous session tracking
$is_unique_view // Boolean for unique vs repeat

// Music tracking
$track_id
$play_duration
$completion_percentage
$play_source // embed, direct, playlist
$player_type // html5, custom
Aggregated Stats Variables
php// Performance metrics
$page_load_time
$time_on_page
$bounce_indicator

// Traffic sources
$traffic_source // organic, direct, social, referral
$utm_campaign
$utm_medium
$utm_source

// User behavior
$clicks_count
$scroll_depth
$interaction_events
Display & Storage Variables
php// Caching
$cache_ttl = 300; // 5 minutes
$batch_size = 100; // Events per batch write
$retention_days = 365; // Data retention

// Display options
$date_range // today, week, month, year
$comparison_period
$chart_type // line, bar, pie
$metric_type // views, plays, duration
Database Schema Variables
sql-- Main events table
event_id (bigint)
event_type (varchar) -- pageview, play, click
object_id (bigint) -- post_id or track_id
timestamp (datetime)
value (float) -- duration, percentage, etc
meta_data (json) -- flexible additional data

-- Aggregated stats table  
stat_date (date)
object_id (bigint)
metric_name (varchar)
metric_value (bigint)
period_type (enum) -- hourly, daily, monthly
Performance Optimization Variables
php// Sampling for high traffic
$sampling_rate = 1; // 1 = track all, 0.1 = track 10%
$sampling_threshold = 1000; // Start sampling above this

// Cleanup
$archive_after_days = 30; // Move to archive tables
$aggregate_interval = 3600; // Hourly aggregation

// Real-time limits
$realtime_buffer_size = 50; // Events before flush
$realtime_timeout = 5; // Seconds before force flush
This structure keeps the analytics lightweight while providing comprehensive tracking without external dependencies. The key is aggressive caching and smart aggregation to prevent database bloat.RetryClaude can make mistakes. Please double-check responses.

GitHub Copilot: I'll transform this WordPress plugin into a lightweight analytics plugin. Here's my step-by-step approach:

## Step 1: Core Plugin Structure Changes

First, I'll update the main plugin file to initialize the analytics system with proper namespacing and constants.

## Step 2: Update Configuration

Add analytics-specific variables to the Config class.

## Step 3: Create Database Schema

Add tables for storing analytics data efficiently.

## Step 4: Implement Tracking System

Create JavaScript tracking and REST API endpoints.

## Step 5: Add Admin UI Components

Create the stats widget and post columns.

### BandfrontAnalytics.php

Update the main plugin file with analytics initialization:

````php
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
````

### [Plugin.php](vscode-remote://ssh-remote/var/www/html/wp-content/plugins/bandfront-analytics/src/Plugin.php)

Replace the Plugin class with analytics functionality:

````php


Made changes.

<?php
namespace bfa;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Main Plugin Class for Bandfront Analytics
 */
class Plugin {
    
    private ?Config $config = null;
    private ?Admin $admin = null;
    private ?Tracker $tracker = null;
    private ?Api $api = null;
    private ?Database $database = null;
    
    public function __construct() {
        // Initialize components
        $this->config = new Config();
        $this->database = new Database();
        $this->tracker = new Tracker($this);
        $this->api = new Api($this);
        
        // Initialize admin only in admin area
        if (is_admin()) {
            $this->admin = new Admin($this);
        }
        
        // Register hooks
        $this->initHooks();
        
        // Schedule cron jobs
        $this->scheduleCronJobs();
    }
    
    /**
     * Initialize plugin hooks
     */
    private function initHooks(): void {
        // Frontend tracking
        add_action('wp_enqueue_scripts', [$this, 'enqueueTrackingScripts']);
        add_action('wp_footer', [$this->tracker, 'outputTrackingData']);
        
        // Admin bar widget
        add_action('admin_bar_menu', [$this, 'addAdminBarWidget'], 100);
        add_action('wp_enqueue_scripts', [$this, 'enqueueAdminBarAssets']);
        add_action('admin_enqueue_scripts', [$this, 'enqueueAdminBarAssets']);
        
        // REST API
        add_action('rest_api_init', [$this->api, 'registerRoutes']);
        
        // Post columns
        add_filter('manage_posts_columns', [$this, 'addViewsColumn']);
        add_action('manage_posts_custom_column', [$this, 'displayViewsColumn'], 10, 2);
        add_filter('manage_pages_columns', [$this, 'addViewsColumn']);
        add_action('manage_pages_custom_column', [$this, 'displayViewsColumn'], 10, 2);
    }
    
    /**
     * Schedule cron jobs for data aggregation and cleanup
     */
    private function scheduleCronJobs(): void {
        if (!wp_next_scheduled('bfa_hourly_aggregation')) {
            wp_schedule_event(time(), 'hourly', 'bfa_hourly_aggregation');
        }
        
        if (!wp_next_scheduled('bfa_daily_cleanup')) {
            wp_schedule_event(time(), 'daily', 'bfa_daily_cleanup');
        }
        
        add_action('bfa_hourly_aggregation', [$this->database, 'aggregateHourlyStats']);
        add_action('bfa_daily_cleanup', [$this->database, 'cleanupOldData']);
    }
    
    /**
     * Enqueue tracking scripts on frontend
     */
    public function enqueueTrackingScripts(): void {
        if (is_user_logged_in() && current_user_can('manage_options') && $this->config->get('exclude_admins')) {
            return;
        }
        
        wp_enqueue_script(
            'bfa-tracker',
            BFA_PLUGIN_URL . 'assets/js/tracker.js',
            [],
            BFA_VERSION,
            true
        );
        
        wp_localize_script('bfa-tracker', 'bfaTracker', [
            'apiUrl' => rest_url('bandfront-analytics/v1/'),
            'nonce' => wp_create_nonce('wp_rest'),
            'sampling' => $this->calculateSamplingRate(),
            'trackingEnabled' => $this->config->get('tracking_enabled', true),
        ]);
    }
    
    /**
     * Calculate sampling rate based on traffic
     */
    private function calculateSamplingRate(): float {
        $dailyViews = $this->database->getTodayPageviews();
        $threshold = $this->config->get('sampling_threshold', 10000);
        
        if ($dailyViews > $threshold) {
            return $this->config->get('sampling_rate', 0.1);
        }
        
        return 1.0;
    }
    
    /**
     * Add admin bar widget
     */
    public function addAdminBarWidget(\WP_Admin_Bar $adminBar): void {
        if (!current_user_can('manage_options')) {
            return;
        }
        
        $stats = $this->database->getQuickStats();
        
        $adminBar->add_node([
            'id' => 'bfa-stats',
            'title' => '<span class="ab-icon dashicons dashicons-chart-bar"></span>' . 
                      '<span class="ab-label">' . number_format($stats['today_views']) . '</span>',
            'href' => admin_url('admin.php?page=bandfront-analytics'),
            'meta' => [
                'title' => __('Today\'s Views', 'bandfront-analytics'),
                'class' => 'bfa-admin-bar-stats'
            ]
        ]);
        
        // Add submenu with mini chart
        $adminBar->add_node([
            'id' => 'bfa-stats-chart',
            'parent' => 'bfa-stats',
            'title' => '<div id="bfa-mini-chart" class="bfa-mini-chart-container"></div>',
            'meta' => [
                'html' => true
            ]
        ]);
    }
    
    /**
     * Enqueue admin bar assets
     */
    public function enqueueAdminBarAssets(): void {
        if (!is_admin_bar_showing() || !current_user_can('manage_options')) {
            return;
        }
        
        wp_enqueue_style(
            'bfa-admin-bar',
            BFA_PLUGIN_URL . 'assets/css/admin-bar.css',
            [],
            BFA_VERSION
        );
        
        wp_enqueue_script(
            'bfa-admin-bar',
            BFA_PLUGIN_URL . 'assets/js/admin-bar.js',
            ['jquery'],
            BFA_VERSION,
            true
        );
        
        wp_localize_script('bfa-admin-bar', 'bfaAdminBar', [
            'apiUrl' => rest_url('bandfront-analytics/v1/'),
            'nonce' => wp_create_nonce('wp_rest'),
        ]);
    }
    
    /**
     * Add views column to posts/pages list
     */
    public function addViewsColumn(array $columns): array {
        $columns['bfa_views'] = '<span class="dashicons dashicons-visibility"></span>';
        return $columns;
    }
    
    /**
     * Display views count in column
     */
    public function displayViewsColumn(string $column, int $postId): void {
        if ($column === 'bfa_views') {
            $views = $this->database->getPostViews($postId);
            echo '<strong>' . number_format($views) . '</strong>';
        }
    }
    
    /**
     * Get component instances
     */
    public function getConfig(): Config {
        return $this->config;
    }
    
    public function getDatabase(): Database {
        return $this->database;
    }
    
    public function getTracker(): Tracker {
        return $this->tracker;
    }
    
    public function getApi(): Api {
        return $this->api;
    }
}
````

### [Config.php](vscode-remote://ssh-remote/var/www/html/wp-content/plugins/bandfront-analytics/src/Config.php)

Update the Config class with analytics-specific settings:

````php


Made changes.

<?php
namespace bfa;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Config Class for Bandfront Analytics
 */
class Config {
    
    private array $settings = [];
    private string $optionName = 'bfa_settings';
    private array $defaults = [];
    
    public function __construct() {
        $this->initializeDefaults();
        $this->loadSettings();
    }
    
    /**
     * Initialize default settings
     */
    private function initializeDefaults(): void {
        $this->defaults = [
            // Core tracking settings
            'tracking_enabled' => true,
            'track_logged_in_users' => true,
            'exclude_admins' => true,
            'anonymize_ip' => true,
            'respect_dnt' => true,
            
            // Performance settings
            'sampling_rate' => 0.1, // 10% sampling when over threshold
            'sampling_threshold' => 10000, // Daily views before sampling kicks in
            'batch_size' => 100, // Events per batch write
            'realtime_buffer_size' => 50, // Events before flush
            'realtime_timeout' => 5, // Seconds before force flush
            
            // Data retention
            'retention_days' => 365, // Keep raw data for 1 year
            'archive_after_days' => 30, // Archive after 30 days
            'aggregate_interval' => 3600, // Hourly aggregation
            
            // Cache settings
            'cache_ttl' => 300, // 5 minutes cache
            'use_object_cache' => true,
            
            // Display settings
            'show_admin_bar_widget' => true,
            'show_dashboard_widget' => true,
            'default_date_range' => 'last_7_days',
            'chart_colors' => [
                'primary' => '#0073aa',
                'secondary' => '#23282d',
                'accent' => '#00a0d2',
            ],
            
            // Music tracking
            'track_music_plays' => true,
            'track_play_duration' => true,
            'track_completion_rate' => true,
            'minimum_play_duration' => 3, // Seconds before counting as play
            
            // Privacy settings
            'privacy_mode' => 'balanced', // strict, balanced, full
            'hash_user_agents' => true,
            'ignore_bots' => true,
            'bot_patterns' => [
                'googlebot', 'bingbot', 'slurp', 'duckduckbot',
                'baiduspider', 'yandexbot', 'facebookexternalhit',
                'twitterbot', 'linkedinbot', 'whatsapp', 'telegram'
            ],
            
            // Advanced tracking
            'track_scroll_depth' => true,
            'track_clicks' => true,
            'track_search_terms' => true,
            'track_404_errors' => true,
            'track_performance' => true,
            
            // API settings
            'api_rate_limit' => 100, // Requests per minute
            'enable_public_api' => false,
            'api_key' => '',
            
            // Export settings
            'export_format' => 'csv', // csv, json, xml
            'compress_exports' => true,
        ];
    }
    
    /**
     * Load settings from WordPress options
     */
    private function loadSettings(): void {
        $saved = get_option($this->optionName, []);
        $this->settings = wp_parse_args($saved, $this->defaults);
    }
    
    /**
     * Save settings to WordPress options
     */
    public function save(array $settings): bool {
        $this->settings = wp_parse_args($settings, $this->settings);
        return update_option($this->optionName, $this->settings);
    }
    
    /**
     * Get a setting value
     */
    public function get(string $key, $default = null) {
        return $this->settings[$key] ?? $default ?? $this->defaults[$key] ?? null;
    }
    
    /**
     * Set a setting value
     */
    public function set(string $key, $value): void {
        $this->settings[$key] = $value;
    }
    
    /**
     * Get all settings
     */
    public function getAll(): array {
        return $this->settings;
    }
    
    /**
     * Check if tracking should be sampled
     */
    public function shouldSample(): bool {
        $rate = $this->get('sampling_rate', 1);
        return $rate < 1 && (mt_rand(1, 100) / 100) > $rate;
    }
    
    /**
     * Check if user agent is a bot
     */
    public function isBot(string $userAgent): bool {
        if (!$this->get('ignore_bots', true)) {
            return false;
        }
        
        $patterns = $this->get('bot_patterns', []);
        $userAgent = strtolower($userAgent);
        
        foreach ($patterns as $pattern) {
            if (strpos($userAgent, $pattern) !== false) {
                return true;
            }
        }
        
        return false;
    }
}
````

### [Database.php](vscode-remote://ssh-remote/var/www/html/wp-content/plugins/bandfront-analytics/src/Database.php)

Create the Database class for handling analytics data:

````php


Made changes.

<?php
namespace bfa;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Database handler for Bandfront Analytics
 */
class Database {
    
    private string $eventsTable;
    private string $statsTable;
    private \wpdb $wpdb;
    
    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->eventsTable = $wpdb->prefix . 'bfa_events';
        $this->statsTable = $wpdb->prefix . 'bfa_stats';
    }
    
    /**
     * Create database tables
     */
    public static function createTables(): void {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // Events table for raw tracking data
        $eventsTable = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}bfa_events (
            event_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            event_type VARCHAR(50) NOT NULL,
            object_id BIGINT UNSIGNED NOT NULL,
            object_type VARCHAR(50) DEFAULT 'post',
            user_hash VARCHAR(32) DEFAULT NULL,
            session_id VARCHAR(32) NOT NULL,
            timestamp DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            value FLOAT DEFAULT NULL,
            referrer_domain VARCHAR(255) DEFAULT NULL,
            user_agent_hash VARCHAR(32) DEFAULT NULL,
            meta_data JSON DEFAULT NULL,
            PRIMARY KEY (event_id),
            KEY idx_type_object (event_type, object_id),
            KEY idx_timestamp (timestamp),
            KEY idx_session (session_id)
        ) $charset_collate";
        
        // Aggregated stats table for performance
        $statsTable = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}bfa_stats (
            stat_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            stat_date DATE NOT NULL,
            stat_hour TINYINT DEFAULT NULL,
            object_id BIGINT UNSIGNED NOT NULL,
            object_type VARCHAR(50) DEFAULT 'post',
            metric_name VARCHAR(50) NOT NULL,
            metric_value BIGINT UNSIGNED NOT NULL DEFAULT 0,
            period_type ENUM('hourly', 'daily', 'monthly') DEFAULT 'daily',
            PRIMARY KEY (stat_id),
            UNIQUE KEY idx_unique_stat (stat_date, stat_hour, object_id, metric_name, period_type),
            KEY idx_date_metric (stat_date, metric_name),
            KEY idx_object (object_id, object_type)
        ) $charset_collate";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($eventsTable);
        dbDelta($statsTable);
    }
    
    /**
     * Record an event
     */
    public function recordEvent(array $data): bool {
        // Apply sampling if needed
        $config = $GLOBALS['BandfrontAnalytics']->getConfig();
        if ($config->shouldSample()) {
            return true; // Pretend success but don't record
        }
        
        // Prepare data
        $eventData = [
            'event_type' => $data['event_type'] ?? 'pageview',
            'object_id' => $data['object_id'] ?? 0,
            'object_type' => $data['object_type'] ?? 'post',
            'user_hash' => $data['user_hash'] ?? null,
            'session_id' => $data['session_id'] ?? '',
            'timestamp' => current_time('mysql'),
            'value' => $data['value'] ?? null,
            'referrer_domain' => $data['referrer_domain'] ?? null,
            'user_agent_hash' => $data['user_agent_hash'] ?? null,
            'meta_data' => !empty($data['meta_data']) ? json_encode($data['meta_data']) : null,
        ];
        
        // Add to batch queue
        $batch = get_transient('bfa_event_batch') ?: [];
        $batch[] = $eventData;
        
        $batchSize = $config->get('batch_size', 100);
        
        if (count($batch) >= $batchSize) {
            $this->flushEventBatch($batch);
            delete_transient('bfa_event_batch');
        } else {
            set_transient('bfa_event_batch', $batch, $config->get('realtime_timeout', 5));
        }
        
        return true;
    }
    
    /**
     * Flush event batch to database
     */
    public function flushEventBatch(array $batch): bool {
        if (empty($batch)) {
            return true;
        }
        
        $values = [];
        $placeholders = [];
        
        foreach ($batch as $event) {
            $placeholders[] = "(%s, %d, %s, %s, %s, %s, %f, %s, %s, %s)";
            
            array_push($values,
                $event['event_type'],
                $event['object_id'],
                $event['object_type'],
                $event['user_hash'],
                $event['session_id'],
                $event['timestamp'],
                $event['value'],
                $event['referrer_domain'],
                $event['user_agent_hash'],
                $event['meta_data']
            );
        }
        
        $query = "INSERT INTO {$this->eventsTable} 
                  (event_type, object_id, object_type, user_hash, session_id, timestamp, value, referrer_domain, user_agent_hash, meta_data) 
                  VALUES " . implode(', ', $placeholders);
        
        return $this->wpdb->query($this->wpdb->prepare($query, $values)) !== false;
    }
    
    /**
     * Get post views count
     */
    public function getPostViews(int $postId): int {
        $cache_key = 'bfa_post_views_' . $postId;
        $views = wp_cache_get($cache_key);
        
        if ($views === false) {
            $views = $this->wpdb->get_var($this->wpdb->prepare(
                "SELECT COALESCE(SUM(metric_value), 0) 
                 FROM {$this->statsTable} 
                 WHERE object_id = %d 
                 AND metric_name = 'pageviews' 
                 AND period_type = 'daily'",
                $postId
            ));
            
            wp_cache_set($cache_key, $views, '', 300); // 5 minute cache
        }
        
        return (int) $views;
    }
    
    /**
     * Get today's pageviews
     */
    public function getTodayPageviews(): int {
        $cache_key = 'bfa_today_pageviews';
        $views = wp_cache_get($cache_key);
        
        if ($views === false) {
            $today = current_time('Y-m-d');
            
            $views = $this->wpdb->get_var($this->wpdb->prepare(
                "SELECT COUNT(*) 
                 FROM {$this->eventsTable} 
                 WHERE event_type = 'pageview' 
                 AND DATE(timestamp) = %s",
                $today
            ));
            
            wp_cache_set($cache_key, $views, '', 60); // 1 minute cache
        }
        
        return (int) $views;
    }
    
    /**
     * Get quick stats for admin bar
     */
    public function getQuickStats(): array {
        $cache_key = 'bfa_quick_stats';
        $stats = wp_cache_get($cache_key);
        
        if ($stats === false) {
            $today = current_time('Y-m-d');
            $yesterday = date('Y-m-d', strtotime('-1 day', current_time('timestamp')));
            
            $stats = [
                'today_views' => $this->getTodayPageviews(),
                'yesterday_views' => $this->wpdb->get_var($this->wpdb->prepare(
                    "SELECT COUNT(*) FROM {$this->eventsTable} 
                     WHERE event_type = 'pageview' AND DATE(timestamp) = %s",
                    $yesterday
                )),
                'today_visitors' => $this->wpdb->get_var($this->wpdb->prepare(
                    "SELECT COUNT(DISTINCT session_id) FROM {$this->eventsTable} 
                     WHERE DATE(timestamp) = %s",
                    $today
                )),
                'active_users' => $this->wpdb->get_var(
                    "SELECT COUNT(DISTINCT session_id) FROM {$this->eventsTable} 
                     WHERE timestamp > DATE_SUB(NOW(), INTERVAL 5 MINUTE)"
                ),
            ];
            
            wp_cache_set($cache_key, $stats, '', 60);
        }
        
        return $stats;
    }
    
    /**
     * Get stats for date range
     */
    public function getStats(string $startDate, string $endDate, string $metric = 'pageviews'): array {
        $cache_key = "bfa_stats_{$startDate}_{$endDate}_{$metric}";
        $stats = wp_cache_get($cache_key);
        
        if ($stats === false) {
            $stats = $this->wpdb->get_results($this->wpdb->prepare(
                "SELECT stat_date, SUM(metric_value) as value 
                 FROM {$this->statsTable} 
                 WHERE stat_date BETWEEN %s AND %s 
                 AND metric_name = %s 
                 AND period_type = 'daily'
                 GROUP BY stat_date 
                 ORDER BY stat_date ASC",
                $startDate,
                $endDate,
                $metric
            ), ARRAY_A);
            
            wp_cache_set($cache_key, $stats, '', 300);
        }
        
        return $stats;
    }
    
    /**
     * Aggregate hourly stats
     */
    public function aggregateHourlyStats(): void {
        $lastHour = date('Y-m-d H:00:00', strtotime('-1 hour'));
        $hour = date('H', strtotime($lastHour));
        $date = date('Y-m-d', strtotime($lastHour));
        
        // Aggregate pageviews
        $this->wpdb->query($this->wpdb->prepare(
            "INSERT INTO {$this->statsTable} (stat_date, stat_hour, object_id, object_type, metric_name, metric_value, period_type)
             SELECT DATE(timestamp), HOUR(timestamp), object_id, object_type, 'pageviews', COUNT(*), 'hourly'
             FROM {$this->eventsTable}
             WHERE event_type = 'pageview'
             AND timestamp >= %s
             AND timestamp < DATE_ADD(%s, INTERVAL 1 HOUR)
             GROUP BY object_id, object_type
             ON DUPLICATE KEY UPDATE metric_value = VALUES(metric_value)",
            $lastHour,
            $lastHour
        ));
        
        // Clear caches
        wp_cache_flush();
    }
    
    /**
     * Clean up old data
     */
    public function cleanupOldData(): void {
        $config = $GLOBALS['BandfrontAnalytics']->getConfig();
        $retentionDays = $config->get('retention_days', 365);
        
        $cutoffDate = date('Y-m-d', strtotime("-{$retentionDays} days"));
        
        // Delete old events
        $this->wpdb->query($this->wpdb->prepare(
            "DELETE FROM {$this->eventsTable} WHERE timestamp < %s",
            $cutoffDate
        ));
        
        // Delete old stats
        $this->wpdb->query($this->wpdb->prepare(
            "DELETE FROM {$this->statsTable} WHERE stat_date < %s",
            $cutoffDate
        ));
    }
}
````

### [Tracker.php](vscode-remote://ssh-remote/var/www/html/wp-content/plugins/bandfront-analytics/src/Tracker.php)

Create the Tracker class for frontend tracking:

````php


Made changes.

<?php
namespace bfa;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Frontend tracking handler
 */
class Tracker {
    
    private Plugin $plugin;
    
    public function __construct(Plugin $plugin) {
        $this->plugin = $plugin;
    }
    
    /**
     * Output tracking data in footer
     */
    public function outputTrackingData(): void {
        if (!$this->shouldTrack()) {
            return;
        }
        
        global $post;
        
        $trackingData = [
            'pageType' => $this->getPageType(),
            'objectId' => is_singular() ? get_the_ID() : 0,
            'objectType' => is_singular() ? get_post_type() : 'archive',
        ];
        
        // Add music tracking data if on a product page
        if (function_exists('is_product') && is_product()) {
            $trackingData['hasMusicPlayer'] = $this->hasAudioFiles($post->ID);
        }
        
        ?>
        <script type="text/javascript">
            window.bfaPageData = <?php echo wp_json_encode($trackingData); ?>;
        </script>
        <?php
    }
    
    /**
     * Check if tracking should be enabled
     */
    private function shouldTrack(): bool {
        $config = $this->plugin->getConfig();
        
        // Check if tracking is enabled
        if (!$config->get('tracking_enabled', true)) {
            return false;
        }
        
        // Check admin exclusion
        if (is_user_logged_in() && current_user_can('manage_options') && $config->get('exclude_admins', true)) {
            return false;
        }
        
        // Check DNT header
        if ($config->get('respect_dnt', true) && !empty($_SERVER['HTTP_DNT']) && $_SERVER['HTTP_DNT'] == '1') {
            return false;
        }
        
        // Check if user agent is a bot
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        if ($config->isBot($userAgent)) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Determine the current page type
     */
    private function getPageType(): string {
        if (is_front_page()) return 'home';
        if (is_single()) return 'single';
        if (is_page()) return 'page';
        if (is_archive()) return 'archive';
        if (is_search()) return 'search';
        if (is_404()) return '404';
        
        return 'other';
    }
    
    /**
     * Check if post has audio files
     */
    private function hasAudioFiles(int $postId): bool {
        // Check for WooCommerce product audio files
        if (function_exists('wc_get_product')) {
            $product = wc_get_product($postId);
            if ($product && method_exists($product, 'get_downloads')) {
                $downloads = $product->get_downloads();
                foreach ($downloads as $download) {
                    if (preg_match('/\.(mp3|ogg|wav|m4a)$/i', $download['file'])) {
                        return true;
                    }
                }
            }
        }
        
        // Check for audio in post content
        $content = get_post_field('post_content', $postId);
        if (strpos($content, '<audio') !== false || strpos($content, '[audio') !== false) {
            return true;
        }
        
        return false;
    }
}
````

### [Api.php](vscode-remote://ssh-remote/var/www/html/wp-content/plugins/bandfront-analytics/src/Api.php)

Create the REST API handler:

````php


Made changes.

<?php
namespace bfa;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * REST API handler for analytics
 */
class Api {
    
    private Plugin $plugin;
    
    public function __construct(Plugin $plugin) {
        $this->plugin = $plugin;
    }
    
    /**
     * Register REST API routes
     */
    public function registerRoutes(): void {
        // Track event endpoint
        register_rest_route('bandfront-analytics/v1', '/track', [
            'methods' => 'POST',
            'callback' => [$this, 'trackEvent'],
            'permission_callback' => '__return_true',
            'args' => [
                'event_type' => [
                    'required' => true,
                    'type' => 'string',
                ],
                'object_id' => [
                    'type' => 'integer',
                    'default' => 0,
                ],
                'value' => [
                    'type' => 'number',
                ],
                'meta' => [
                    'type' => 'object',
                ],
            ],
        ]);
        
        // Stats endpoint
        register_rest_route('bandfront-analytics/v1', '/stats', [
            'methods' => 'GET',
            'callback' => [$this, 'getStats'],
            'permission_callback' => [$this, 'checkStatsPermission'],
            'args' => [
                'start_date' => [
                    'type' => 'string',
                    'format' => 'date',
                ],
                'end_date' => [
                    'type' => 'string',
                    'format' => 'date',
                ],
                'metric' => [
                    'type' => 'string',
                    'default' => 'pageviews',
                ],
            ],
        ]);
        
        // Quick stats endpoint
        register_rest_route('bandfront-analytics/v1', '/quick-stats', [
            'methods' => 'GET',
            'callback' => [$this, 'getQuickStats'],
            'permission_callback' => [$this, 'checkStatsPermission'],
        ]);
        
        // Chart data endpoint
        register_rest_route('bandfront-analytics/v1', '/chart', [
            'methods' => 'GET',
            'callback' => [$this, 'getChartData'],
            'permission_callback' => [$this, 'checkStatsPermission'],
            'args' => [
                'days' => [
                    'type' => 'integer',
                    'default' => 7,
                ],
            ],
        ]);
    }
    
    /**
     * Track an event via API
     */
    public function trackEvent(\WP_REST_Request $request): \WP_REST_Response {
        $eventType = $request->get_param('event_type');
        $objectId = $request->get_param('object_id');
        $value = $request->get_param('value');
        $meta = $request->get_param('meta');
        
        // Generate session ID
        $sessionId = $this->getSessionId();
        
        // Hash user data for privacy
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $userAgentHash = md5($userAgent);
        
        // Get referrer domain
        $referrer = wp_get_referer();
        $referrerDomain = $referrer ? parse_url($referrer, PHP_URL_HOST) : null;
        
        // Record event
        $success = $this->plugin->getDatabase()->recordEvent([
            'event_type' => $eventType,
            'object_id' => $objectId,
            'object_type' => get_post_type($objectId) ?: 'unknown',
            'session_id' => $sessionId,
            'value' => $value,
            'referrer_domain' => $referrerDomain,
            'user_agent_hash' => $userAgentHash,
            'meta_data' => $meta,
        ]);
        
        return new \WP_REST_Response([
            'success' => $success,
            'session_id' => $sessionId,
        ]);
    }
    
    /**
     * Get stats via API
     */
    public function getStats(\WP_REST_Request $request): \WP_REST_Response {
        $startDate = $request->get_param('start_date') ?: date('Y-m-d', strtotime('-7 days'));
        $endDate = $request->get_param('end_date') ?: date('Y-m-d');
        $metric = $request->get_param('metric');
        
        $stats = $this->plugin->getDatabase()->getStats($startDate, $endDate, $metric);
        
        return new \WP_REST_Response([
            'stats' => $stats,
            'period' => [
                'start' => $startDate,
                'end' => $endDate,
            ],
            'metric' => $metric,
        ]);
    }
    
    /**
     * Get quick stats
     */
    public function getQuickStats(): \WP_REST_Response {
        $stats = $this->plugin->getDatabase()->getQuickStats();
        
        return new \WP_REST_Response($stats);
    }
    
    /**
     * Get chart data for admin bar
     */
    public function getChartData(\WP_REST_Request $request): \WP_REST_Response {
        $days = $request->get_param('days');
        $endDate = date('Y-m-d');
        $startDate = date('Y-m-d', strtotime("-{$days} days"));
        
        $stats = $this->plugin->getDatabase()->getStats($startDate, $endDate, 'pageviews');
        
        // Format for Chart.js
        $labels = [];
        $data = [];
        
        foreach ($stats as $stat) {
            $labels[] = date('M j', strtotime($stat['stat_date']));
            $data[] = (int) $stat['value'];
        }
        
        return new \WP_REST_Response([
            'labels' => $labels,
            'datasets' => [
                [
                    'label' => __('Page Views', 'bandfront-analytics'),
                    'data' => $data,
                    'borderColor' => '#0073aa',
                    'backgroundColor' => 'rgba(0, 115, 170, 0.1)',
                ],
            ],
        ]);
    }
    
    /**
     * Check permission for viewing stats
     */
    public function checkStatsPermission(): bool {
        return current_user_can('manage_options');
    }
    
    /**
     * Get or create session ID
     */
    private function getSessionId(): string {
        if (!session_id()) {
            session_start();
        }
        
        if (!isset($_SESSION['bfa_session_id'])) {
            $_SESSION['bfa_session_id'] = md5(uniqid('bfa_', true));
        }
        
        return $_SESSION['bfa_session_id'];
    }
}
````

### [Admin.php](vscode-remote://ssh-remote/var/www/html/wp-content/plugins/bandfront-analytics/src/Admin.php)

Update the Admin class for analytics dashboard:

````php


Made changes.

<?php
namespace bfa;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Admin interface for Bandfront Analytics
 */
class Admin {
    
    private Plugin $plugin;
    
    public function __construct(Plugin $plugin) {
        $this->plugin = $plugin;
        $this->initHooks();
    }
    
    /**
     * Initialize admin hooks
     */
    private function initHooks(): void {
        add_action('admin_menu', [$this, 'addMenuPages']);
        add_action('admin_enqueue_scripts', [$this, 'enqueueAdminAssets']);
        add_action('wp_dashboard_setup', [$this, 'addDashboardWidget']);
        add_action('wp_ajax_bfa_save_settings', [$this, 'ajaxSaveSettings']);
    }
    
    /**
     * Add admin menu pages
     */
    public function addMenuPages(): void {
        // Main analytics page
        add_menu_page(
            __('Analytics', 'bandfront-analytics'),
            __('Analytics', 'bandfront-analytics'),
            'manage_options',
            'bandfront-analytics',
            [$this, 'renderAnalyticsPage'],
            'dashicons-chart-bar',
            25
        );
        
        // Settings submenu
        add_submenu_page(
            'bandfront-analytics',
            __('Analytics Settings', 'bandfront-analytics'),
            __('Settings', 'bandfront-analytics'),
            'manage_options',
            'bandfront-analytics-settings',
            [$this, 'renderSettingsPage']
        );
    }
    
    /**
     * Enqueue admin assets
     */
    public function enqueueAdminAssets(string $hook): void {
        if (!in_array($hook, ['toplevel_page_bandfront-analytics', 'analytics_page_bandfront-analytics-settings'])) {
            return;
        }
        
        // Enqueue Chart.js
        wp_enqueue_script(
            'chart-js',
            'https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js',
            [],
            '4.4.0'
        );
        
        // Enqueue admin styles
        wp_enqueue_style(
            'bfa-admin',
            BFA_PLUGIN_URL . 'assets/css/admin.css',
            [],
            BFA_VERSION
        );
        
        // Enqueue admin scripts
        wp_enqueue_script(
            'bfa-admin',
            BFA_PLUGIN_URL . 'assets/js/admin.js',
            ['jquery', 'chart-js'],
            BFA_VERSION,
            true
        );
        
        wp_localize_script('bfa-admin', 'bfaAdmin', [
            'apiUrl' => rest_url('bandfront-analytics/v1/'),
            'nonce' => wp_create_nonce('wp_rest'),
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'ajaxNonce' => wp_create_nonce('bfa_ajax'),
        ]);
    }
    
    /**
     * Render analytics dashboard page
     */
    public function renderAnalyticsPage(): void {
        $database = $this->plugin->getDatabase();
        $quickStats = $database->getQuickStats();
        
        ?>
        <div class="wrap bfa-analytics-wrap">
            <h1><?php echo esc_html__('Analytics Dashboard', 'bandfront-analytics'); ?></h1>
            
            <!-- Quick Stats -->
            <div class="bfa-stats-grid">
                <div class="bfa-stat-card">
                    <div class="bfa-stat-icon">👁️</div>
                    <div class="bfa-stat-content">
                        <div class="bfa-stat-value"><?php echo number_format($quickStats['today_views']); ?></div>
                        <div class="bfa-stat-label"><?php esc_html_e('Views Today', 'bandfront-analytics'); ?></div>
                    </div>
                </div>
                
                <div class="bfa-stat-card">
                    <div class="bfa-stat-icon">👥</div>
                    <div class="bfa-stat-content">
                        <div class="bfa-stat-value"><?php echo number_format($quickStats['today_visitors']); ?></div>
                        <div class="bfa-stat-label"><?php esc_html_e('Visitors Today', 'bandfront-analytics'); ?></div>
                    </div>
                </div>
                
                <div class="bfa-stat-card">
                    <div class="bfa-stat-icon">🟢</div>
                    <div class="bfa-stat-content">
                        <div class="bfa-stat-value"><?php echo number_format($quickStats['active_users']); ?></div>
                        <div class="bfa-stat-label"><?php esc_html_e('Active Now', 'bandfront-analytics'); ?></div>
                    </div>
                </div>
                
                <div class="bfa-stat-card">
                    <?php
                    $change = $quickStats['yesterday_views'] > 0 ? 
                              round((($quickStats['today_views'] - $quickStats['yesterday_views']) / $quickStats['yesterday_views']) * 100, 1) : 0;
                    $changeClass = $change >= 0 ? 'positive' : 'negative';
                    ?>
                    <div class="bfa-stat-icon">📈</div>
                    <div class="bfa-stat-content">
                        <div class="bfa-stat-value bfa-change-<?php echo $changeClass; ?>">
                            <?php echo ($change >= 0 ? '+' : '') . $change; ?>%
                        </div>
                        <div class="bfa-stat-label"><?php esc_html_e('vs Yesterday', 'bandfront-analytics'); ?></div>
                    </div>
                </div>
            </div>
            
            <!-- Date Range Selector -->
            <div class="bfa-controls">
                <select id="bfa-date-range" class="bfa-date-range">
                    <option value="7"><?php esc_html_e('Last 7 days', 'bandfront-analytics'); ?></option>
                    <option value="30"><?php esc_html_e('Last 30 days', 'bandfront-analytics'); ?></option>
                    <option value="90"><?php esc_html_e('Last 90 days', 'bandfront-analytics'); ?></option>
                </select>
            </div>
            
            <!-- Main Chart -->
            <div class="bfa-chart-container">
                <canvas id="bfa-main-chart"></canvas>
            </div>
            
            <!-- Top Content -->
            <div class="bfa-top-content">
                <h2><?php esc_html_e('Top Content', 'bandfront-analytics'); ?></h2>
                <div id="bfa-top-posts">
                    <!-- Loaded via AJAX -->
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render settings page
     */
    public function renderSettingsPage(): void {
        if (isset($_POST['bfa_save_settings']) && wp_verify_nonce($_POST['bfa_nonce'], 'bfa_settings')) {
            $this->saveSettings();
        }
        
        $config = $this->plugin->getConfig();
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Analytics Settings', 'bandfront-analytics'); ?></h1>
            
            <form method="post" action="">
                <?php wp_nonce_field('bfa_settings', 'bfa_nonce'); ?>
                
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php esc_html_e('Enable Tracking', 'bandfront-analytics'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="tracking_enabled" value="1" 
                                       <?php checked($config->get('tracking_enabled')); ?>>
                                <?php esc_html_e('Enable analytics tracking', 'bandfront-analytics'); ?>
                            </label>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><?php esc_html_e('Privacy Settings', 'bandfront-analytics'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="exclude_admins" value="1" 
                                       <?php checked($config->get('exclude_admins')); ?>>
                                <?php esc_html_e('Exclude administrators from tracking', 'bandfront-analytics'); ?>
                            </label><br>
                            
                            <label>
                                <input type="checkbox" name="respect_dnt" value="1" 
                                       <?php checked($config->get('respect_dnt')); ?>>
                                <?php esc_html_e('Respect Do Not Track header', 'bandfront-analytics'); ?>
                            </label><br>
                            
                            <label>
                                <input type="checkbox" name="anonymize_ip" value="1" 
                                       <?php checked($config->get('anonymize_ip')); ?>>
                                <?php esc_html_e('Anonymize IP addresses', 'bandfront-analytics'); ?>
                            </label>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><?php esc_html_e('Performance', 'bandfront-analytics'); ?></th>
                        <td>
                            <label>
                                <?php esc_html_e('Sampling threshold', 'bandfront-analytics'); ?><br>
                                <input type="number" name="sampling_threshold" 
                                       value="<?php echo esc_attr($config->get('sampling_threshold')); ?>">
                                <p class="description">
                                    <?php esc_html_e('Start sampling when daily views exceed this number', 'bandfront-analytics'); ?>
                                </p>
                            </label>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><?php esc_html_e('Data Retention', 'bandfront-analytics'); ?></th>
                        <td>
                            <label>
                                <?php esc_html_e('Keep data for', 'bandfront-analytics'); ?><br>
                                <input type="number" name="retention_days" 
                                       value="<?php echo esc_attr($config->get('retention_days')); ?>">
                                <?php esc_html_e('days', 'bandfront-analytics'); ?>
                            </label>
                        </td>
                    </tr>
                </table>
                
                <p class="submit">
                    <input type="submit" name="bfa_save_settings" class="button-primary" 
                           value="<?php esc_attr_e('Save Settings', 'bandfront-analytics'); ?>">
                </p>
            </form>
        </div>
        <?php
    }
    
    /**
     * Save settings
     */
    private function saveSettings(): void {
        $settings = [
            'tracking_enabled' => !empty($_POST['tracking_enabled']),
            'exclude_admins' => !empty($_POST['exclude_admins']),
            'respect_dnt' => !empty($_POST['respect_dnt']),
            'anonymize_ip' => !empty($_POST['anonymize_ip']),
            'sampling_threshold' => intval($_POST['sampling_threshold'] ?? 10000),
            'retention_days' => intval($_POST['retention_days'] ?? 365),
        ];
        
        $this->plugin->getConfig()->save($settings);
        
        add_settings_error(
            'bfa_messages',
            'bfa_message',
            __('Settings saved successfully!', 'bandfront-analytics'),
            'success'
        );
    }
    
    /**
     * Add dashboard widget
     */
    public function addDashboardWidget(): void {
        if (!$this->plugin->getConfig()->get('show_dashboard_widget', true)) {
            return;
        }
        
        wp_add_dashboard_widget(
            'bfa_dashboard_widget',
            __('Analytics Overview', 'bandfront-analytics'),
            [$this, 'renderDashboardWidget']
        );
    }
    
    /**
     * Render dashboard widget
     */
    public function renderDashboardWidget(): void {
        $quickStats = $this->plugin->getDatabase()->getQuickStats();
        ?>
        <div class="bfa-dashboard-widget">
            <div class="bfa-widget-stats">
                <div class="bfa-widget-stat">
                    <strong><?php echo number_format($quickStats['today_views']); ?></strong>
                    <span><?php esc_html_e('Views Today', 'bandfront-analytics'); ?></span>
                </div>
                <div class="bfa-widget-stat">
                    <strong><?php echo number_format($quickStats['today_visitors']); ?></strong>
                    <span><?php esc_html_e('Visitors', 'bandfront-analytics'); ?></span>
                </div>
            </div>
            <div class="bfa-widget-chart">
                <canvas id="bfa-dashboard-chart" height="150"></canvas>
            </div>
            <p class="bfa-widget-footer">
                <a href="<?php echo admin_url('admin.php?page=bandfront-analytics'); ?>">
                    <?php esc_html_e('View Full Report', 'bandfront-analytics'); ?> →
                </a>
            </p>
        </div>
        <?php
    }
}
````

### [tracker.js](vscode-remote://ssh-remote/var/www/html/wp-content/plugins/bandfront-analytics/assets/js/tracker.js)

Create the frontend tracking JavaScript:

````javascript


Made changes.

(function() {
    'use strict';
    
    // Check if tracking is enabled
    if (!window.bfaTracker || !window.bfaTracker.trackingEnabled) {
        return;
    }
    
    // Check sampling
    if (window.bfaTracker.sampling < 1 && Math.random() > window.bfaTracker.sampling) {
        return;
    }
    
    // Track pageview on load
    document.addEventListener('DOMContentLoaded', function() {
        trackEvent('pageview', {
            object_id: window.bfaPageData?.objectId || 0,
            meta: {
                page_type: window.bfaPageData?.pageType || 'unknown',
                referrer: document.referrer,
                viewport: window.innerWidth + 'x' + window.innerHeight,
            }
        });
        
        // Track scroll depth
        let maxScroll = 0;
        let scrollTimer = null;
        
        window.addEventListener('scroll', function() {
            const scrollPercent = Math.round((window.scrollY + window.innerHeight) / document.body.scrollHeight * 100);
            if (scrollPercent > maxScroll) {
                maxScroll = scrollPercent;
                
                clearTimeout(scrollTimer);
                scrollTimer = setTimeout(function() {
                    trackEvent('scroll', {
                        value: maxScroll,
                        object_id: window.bfaPageData?.objectId || 0
                    });
                }, 1000);
            }
        });
        
        // Track time on page
        let startTime = Date.now();
        window.addEventListener('beforeunload', function() {
            const timeOnPage = Math.round((Date.now() - startTime) / 1000);
            if (timeOnPage > 3) { // Only track if more than 3 seconds
                navigator.sendBeacon(
                    window.bfaTracker.apiUrl + 'track',
                    JSON.stringify({
                        event_type: 'time_on_page',
                        value: timeOnPage,
                        object_id: window.bfaPageData?.objectId || 0
                    })
                );
            }
        });
        
        // Track music plays
        trackMusicPlays();
    });
    
    /**
     * Track an event
     */
    function trackEvent(eventType, data) {
        const eventData = {
            event_type: eventType,
            ...data
        };
        
        fetch(window.bfaTracker.apiUrl + 'track', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-WP-Nonce': window.bfaTracker.nonce
            },
            body: JSON.stringify(eventData)
        }).catch(function(error) {
            console.error('BFA tracking error:', error);
        });
    }
    
    /**
     * Track music player events
     */
    function trackMusicPlays() {
        // Track HTML5 audio elements
        const audioElements = document.querySelectorAll('audio');
        
        audioElements.forEach(function(audio, index) {
            let playStartTime = null;
            let totalPlayTime = 0;
            let hasTrackedPlay = false;
            
            // Track play event
            audio.addEventListener('play', function() {
                playStartTime = Date.now();
                
                if (!hasTrackedPlay) {
                    hasTrackedPlay = true;
                    trackEvent('music_play', {
                        object_id: window.bfaPageData?.objectId || 0,
                        meta: {
                            track_index: index,
                            track_src: audio.src,
                            duration: audio.duration
                        }
                    });
                }
            });
            
            // Track pause/end to calculate play duration
            const trackPlayDuration = function() {
                if (playStartTime) {
                    totalPlayTime += (Date.now() - playStartTime) / 1000;
                    playStartTime = null;
                    
                    // Track completion percentage
                    const completionPercent = audio.duration > 0 ? 
                        Math.round((audio.currentTime / audio.duration) * 100) : 0;
                    
                    trackEvent('music_duration', {
                        value: totalPlayTime,
                        object_id: window.bfaPageData?.objectId || 0,
                        meta: {
                            track_index: index,
                            completion: completionPercent
                        }
                    });
                }
            };
            
            audio.addEventListener('pause', trackPlayDuration);
            audio.addEventListener('ended', trackPlayDuration);
        });
        
        // Track MediaElement.js players
        if (window.mejs && window.mejs.players) {
            Object.values(window.mejs.players).forEach(function(player, index) {
                let playStartTime = null;
                let hasTrackedPlay = false;
                
                player.media.addEventListener('play', function() {
                    playStartTime = Date.now();
                    
                    if (!hasTrackedPlay) {
                        hasTrackedPlay = true;
                        trackEvent('music_play', {
                            object_id: window.bfaPageData?.objectId || 0,
                            meta: {
                                player_type: 'mediaelement',
                                track_index: index
                            }
                        });
                    }
                });
            });
        }
    }
    
    // Expose API for manual tracking
    window.bfaTrack = trackEvent;
})();
````

### [admin.css](vscode-remote://ssh-remote/var/www/html/wp-content/plugins/bandfront-analytics/assets/css/admin.css)

Create admin styles:

````css


Made changes.

/* Analytics Dashboard Styles */
.bfa-analytics-wrap {
    margin: 20px 20px 20px 0;
}

.bfa-stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.bfa-stat-card {
    background: #fff;
    border: 1px solid #e0e0e0;
    border-radius: 8px;
    padding: 20px;
    display: flex;
    align-items: center;
    gap: 15px;
    transition: box-shadow 0.3s ease;
}

.bfa-stat-card:hover {
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
}

.bfa-stat-icon {
    font-size: 32px;
    opacity: 0.8;
}

.bfa-stat-content {
    flex: 1;
}

.bfa-stat-value {
    font-size: 28px;
    font-weight: 600;
    color: #23282d;
    line-height: 1;
}

.bfa-stat-label {
    color: #666;
    font-size: 14px;
    margin-top: 5px;
}

.bfa-change-positive {
    color: #46b450;
}

.bfa-change-negative {
    color: #dc3232;
}

.bfa-controls {
    margin-bottom: 20px;
}

.bfa-date-range {
    padding: 8px 12px;
    border: 1px solid #ddd;
    border-radius: 4px;
    font-size: 14px;
}

.bfa-chart-container {
    background: #fff;
    border: 1px solid #e0e0e0;
    border-radius: 8px;
    padding: 20px;
    margin-bottom: 30px;
    height: 400px;
    position: relative;
}

.bfa-top-content {
    background: #fff;
    border: 1px solid #e0e0e0;
    border-radius: 8px;
    padding: 20px;
}

.bfa-top-content h2 {
    margin-top: 0;
    

Similar code found with 2 license types

100mountains: continue from this step 

Admin.php
Update the Admin class for analytics dashboard:


GitHub Copilot: I'll continue updating the Admin class and complete the analytics plugin transformation. Here are the remaining files needed:

### [Tracker.php](vscode-remote://ssh-remote/var/www/html/wp-content/plugins/bandfront-analytics/src/Tracker.php)

Create the Tracker class for frontend tracking:

````php
<?php
namespace bfa;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Frontend tracking handler
 */
class Tracker {
    
    private Plugin $plugin;
    
    public function __construct(Plugin $plugin) {
        $this->plugin = $plugin;
    }
    
    /**
     * Output tracking data in footer
     */
    public function outputTrackingData(): void {
        if (!$this->shouldTrack()) {
            return;
        }
        
        global $post;
        
        $trackingData = [
            'pageType' => $this->getPageType(),
            'objectId' => is_singular() ? get_the_ID() : 0,
            'objectType' => is_singular() ? get_post_type() : 'archive',
        ];
        
        // Add music tracking data if on a product page
        if (function_exists('is_product') && is_product()) {
            $trackingData['hasMusicPlayer'] = $this->hasAudioFiles($post->ID);
        }
        
        ?>
        <script type="text/javascript">
            window.bfaPageData = <?php echo wp_json_encode($trackingData); ?>;
        </script>
        <?php
    }
    
    /**
     * Check if tracking should be enabled
     */
    private function shouldTrack(): bool {
        $config = $this->plugin->getConfig();
        
        // Check if tracking is enabled
        if (!$config->get('tracking_enabled', true)) {
            return false;
        }
        
        // Check admin exclusion
        if (is_user_logged_in() && current_user_can('manage_options') && $config->get('exclude_admins', true)) {
            return false;
        }
        
        // Check DNT header
        if ($config->get('respect_dnt', true) && !empty($_SERVER['HTTP_DNT']) && $_SERVER['HTTP_DNT'] == '1') {
            return false;
        }
        
        // Check if user agent is a bot
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        if ($config->isBot($userAgent)) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Determine the current page type
     */
    private function getPageType(): string {
        if (is_front_page()) return 'home';
        if (is_single()) return 'single';
        if (is_page()) return 'page';
        if (is_archive()) return 'archive';
        if (is_search()) return 'search';
        if (is_404()) return '404';
        
        return 'other';
    }
    
    /**
     * Check if post has audio files
     */
    private function hasAudioFiles(int $postId): bool {
        // Check for WooCommerce product audio files
        if (function_exists('wc_get_product')) {
            $product = wc_get_product($postId);
            if ($product && method_exists($product, 'get_downloads')) {
                $downloads = $product->get_downloads();
                foreach ($downloads as $download) {
                    if (preg_match('/\.(mp3|ogg|wav|m4a)$/i', $download['file'])) {
                        return true;
                    }
                }
            }
        }
        
        // Check for audio in post content
        $content = get_post_field('post_content', $postId);
        if (strpos($content, '<audio') !== false || strpos($content, '[audio') !== false) {
            return true;
        }
        
        return false;
    }
}
````

### [Api.php](vscode-remote://ssh-remote/var/www/html/wp-content/plugins/bandfront-analytics/src/Api.php)

Create the REST API handler:

````php


Made changes.

<?php
namespace bfa;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * REST API handler for analytics
 */
class Api {
    
    private Plugin $plugin;
    
    public function __construct(Plugin $plugin) {
        $this->plugin = $plugin;
    }
    
    /**
     * Register REST API routes
     */
    public function registerRoutes(): void {
        // Track event endpoint
        register_rest_route('bandfront-analytics/v1', '/track', [
            'methods' => 'POST',
            'callback' => [$this, 'trackEvent'],
            'permission_callback' => '__return_true',
            'args' => [
                'event_type' => [
                    'required' => true,
                    'type' => 'string',
                ],
                'object_id' => [
                    'type' => 'integer',
                    'default' => 0,
                ],
                'value' => [
                    'type' => 'number',
                ],
                'meta' => [
                    'type' => 'object',
                ],
            ],
        ]);
        
        // Stats endpoint
        register_rest_route('bandfront-analytics/v1', '/stats', [
            'methods' => 'GET',
            'callback' => [$this, 'getStats'],
            'permission_callback' => [$this, 'checkStatsPermission'],
            'args' => [
                'start_date' => [
                    'type' => 'string',
                    'format' => 'date',
                ],
                'end_date' => [
                    'type' => 'string',
                    'format' => 'date',
                ],
                'metric' => [
                    'type' => 'string',
                    'default' => 'pageviews',
                ],
            ],
        ]);
        
        // Quick stats endpoint
        register_rest_route('bandfront-analytics/v1', '/quick-stats', [
            'methods' => 'GET',
            'callback' => [$this, 'getQuickStats'],
            'permission_callback' => [$this, 'checkStatsPermission'],
        ]);
        
        // Chart data endpoint
        register_rest_route('bandfront-analytics/v1', '/chart', [
            'methods' => 'GET',
            'callback' => [$this, 'getChartData'],
            'permission_callback' => [$this, 'checkStatsPermission'],
            'args' => [
                'days' => [
                    'type' => 'integer',
                    'default' => 7,
                ],
            ],
        ]);
        
        // Top posts endpoint
        register_rest_route('bandfront-analytics/v1', '/top-posts', [
            'methods' => 'GET',
            'callback' => [$this, 'getTopPosts'],
            'permission_callback' => [$this, 'checkStatsPermission'],
            'args' => [
                'limit' => [
                    'type' => 'integer',
                    'default' => 10,
                ],
                'days' => [
                    'type' => 'integer',
                    'default' => 7,
                ],
            ],
        ]);
    }
    
    /**
     * Track an event via API
     */
    public function trackEvent(\WP_REST_Request $request): \WP_REST_Response {
        $eventType = $request->get_param('event_type');
        $objectId = $request->get_param('object_id');
        $value = $request->get_param('value');
        $meta = $request->get_param('meta');
        
        // Generate session ID
        $sessionId = $this->getSessionId();
        
        // Hash user data for privacy
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $userAgentHash = md5($userAgent);
        
        // Get referrer domain
        $referrer = wp_get_referer();
        $referrerDomain = $referrer ? parse_url($referrer, PHP_URL_HOST) : null;
        
        // Record event
        $success = $this->plugin->getDatabase()->recordEvent([
            'event_type' => $eventType,
            'object_id' => $objectId,
            'object_type' => get_post_type($objectId) ?: 'unknown',
            'session_id' => $sessionId,
            'value' => $value,
            'referrer_domain' => $referrerDomain,
            'user_agent_hash' => $userAgentHash,
            'meta_data' => $meta,
        ]);
        
        return new \WP_REST_Response([
            'success' => $success,
            'session_id' => $sessionId,
        ]);
    }
    
    /**
     * Get stats via API
     */
    public function getStats(\WP_REST_Request $request): \WP_REST_Response {
        $startDate = $request->get_param('start_date') ?: date('Y-m-d', strtotime('-7 days'));
        $endDate = $request->get_param('end_date') ?: date('Y-m-d');
        $metric = $request->get_param('metric');
        
        $stats = $this->plugin->getDatabase()->getStats($startDate, $endDate, $metric);
        
        return new \WP_REST_Response([
            'stats' => $stats,
            'period' => [
                'start' => $startDate,
                'end' => $endDate,
            ],
            'metric' => $metric,
        ]);
    }
    
    /**
     * Get quick stats
     */
    public function getQuickStats(): \WP_REST_Response {
        $stats = $this->plugin->getDatabase()->getQuickStats();
        
        return new \WP_REST_Response($stats);
    }
    
    /**
     * Get chart data for admin bar
     */
    public function getChartData(\WP_REST_Request $request): \WP_REST_Response {
        $days = $request->get_param('days');
        $endDate = date('Y-m-d');
        $startDate = date('Y-m-d', strtotime("-{$days} days"));
        
        $stats = $this->plugin->getDatabase()->getStats($startDate, $endDate, 'pageviews');
        
        // Format for Chart.js
        $labels = [];
        $data = [];
        
        // Create date range
        $period = new \DatePeriod(
            new \DateTime($startDate),
            new \DateInterval('P1D'),
            (new \DateTime($endDate))->modify('+1 day')
        );
        
        // Build date map
        $dateMap = [];
        foreach ($stats as $stat) {
            $dateMap[$stat['stat_date']] = (int) $stat['value'];
        }
        
        // Fill in missing dates with 0
        foreach ($period as $date) {
            $dateStr = $date->format('Y-m-d');
            $labels[] = $date->format('M j');
            $data[] = $dateMap[$dateStr] ?? 0;
        }
        
        return new \WP_REST_Response([
            'labels' => $labels,
            'datasets' => [
                [
                    'label' => __('Page Views', 'bandfront-analytics'),
                    'data' => $data,
                    'borderColor' => '#0073aa',
                    'backgroundColor' => 'rgba(0, 115, 170, 0.1)',
                    'tension' => 0.1,
                ],
            ],
        ]);
    }
    
    /**
     * Get top posts
     */
    public function getTopPosts(\WP_REST_Request $request): \WP_REST_Response {
        $limit = $request->get_param('limit');
        $days = $request->get_param('days');
        
        $topPosts = $this->plugin->getDatabase()->getTopPosts($limit, $days);
        
        // Enhance with post data
        foreach ($topPosts as &$post) {
            $postObj = get_post($post['object_id']);
            if ($postObj) {
                $post['title'] = $postObj->post_title;
                $post['url'] = get_permalink($postObj);
                $post['type'] = $postObj->post_type;
            }
        }
        
        return new \WP_REST_Response($topPosts);
    }
    
    /**
     * Check permission for viewing stats
     */
    public function checkStatsPermission(): bool {
        return current_user_can('manage_options');
    }
    
    /**
     * Get or create session ID
     */
    private function getSessionId(): string {
        if (!session_id()) {
            session_start();
        }
        
        if (!isset($_SESSION['bfa_session_id'])) {
            $_SESSION['bfa_session_id'] = md5(uniqid('bfa_', true));
        }
        
        return $_SESSION['bfa_session_id'];
    }
}
````

### [Database.php](vscode-remote://ssh-remote/var/www/html/wp-content/plugins/bandfront-analytics/src/Database.php)

Update the Database class with additional methods:

````php


Made changes.

<?php
namespace bfa;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Database handler for Bandfront Analytics
 */
class Database {
    
    // ...existing code...
    
    /**
     * Get top posts for a period
     */
    public function getTopPosts(int $limit = 10, int $days = 7): array {
        $cache_key = "bfa_top_posts_{$limit}_{$days}";
        $posts = wp_cache_get($cache_key);
        
        if ($posts === false) {
            $startDate = date('Y-m-d', strtotime("-{$days} days"));
            
            $posts = $this->wpdb->get_results($this->wpdb->prepare(
                "SELECT object_id, COUNT(*) as views 
                 FROM {$this->eventsTable} 
                 WHERE event_type = 'pageview' 
                 AND object_type IN ('post', 'page')
                 AND timestamp >= %s
                 GROUP BY object_id 
                 ORDER BY views DESC 
                 LIMIT %d",
                $startDate,
                $limit
            ), ARRAY_A);
            
            wp_cache_set($cache_key, $posts, '', 300);
        }
        
        return $posts;
    }
    
    /**
     * Get music play stats
     */
    public function getMusicStats(string $startDate, string $endDate): array {
        $cache_key = "bfa_music_stats_{$startDate}_{$endDate}";
        $stats = wp_cache_get($cache_key);
        
        if ($stats === false) {
            $stats = [
                'total_plays' => $this->wpdb->get_var($this->wpdb->prepare(
                    "SELECT COUNT(*) FROM {$this->eventsTable} 
                     WHERE event_type = 'music_play' 
                     AND timestamp BETWEEN %s AND %s",
                    $startDate . ' 00:00:00',
                    $endDate . ' 23:59:59'
                )),
                'unique_tracks' => $this->wpdb->get_var($this->wpdb->prepare(
                    "SELECT COUNT(DISTINCT object_id) FROM {$this->eventsTable} 
                     WHERE event_type = 'music_play' 
                     AND timestamp BETWEEN %s AND %s",
                    $startDate . ' 00:00:00',
                    $endDate . ' 23:59:59'
                )),
                'avg_duration' => $this->wpdb->get_var($this->wpdb->prepare(
                    "SELECT AVG(value) FROM {$this->eventsTable} 
                     WHERE event_type = 'music_duration' 
                     AND timestamp BETWEEN %s AND %s",
                    $startDate . ' 00:00:00',
                    $endDate . ' 23:59:59'
                )),
            ];
            
            wp_cache_set($cache_key, $stats, '', 300);
        }
        
        return $stats;
    }
    
    /**
     * Aggregate daily stats
     */
    public function aggregateDailyStats(): void {
        $yesterday = date('Y-m-d', strtotime('-1 day'));
        
        // Aggregate by object and metric
        $metrics = ['pageviews', 'music_plays'];
        
        foreach ($metrics as $metric) {
            $eventType = $metric === 'pageviews' ? 'pageview' : 'music_play';
            
            $this->wpdb->query($this->wpdb->prepare(
                "INSERT INTO {$this->statsTable} (stat_date, object_id, object_type, metric_name, metric_value, period_type)
                 SELECT DATE(timestamp), object_id, object_type, %s, COUNT(*), 'daily'
                 FROM {$this->eventsTable}
                 WHERE event_type = %s
                 AND DATE(timestamp) = %s
                 GROUP BY object_id, object_type
                 ON DUPLICATE KEY UPDATE metric_value = VALUES(metric_value)",
                $metric,
                $eventType,
                $yesterday
            ));
        }
        
        // Also create site-wide daily totals
        $this->wpdb->query($this->wpdb->prepare(
            "INSERT INTO {$this->statsTable} (stat_date, object_id, object_type, metric_name, metric_value, period_type)
             SELECT %s, 0, 'site', 'total_pageviews', COUNT(*), 'daily'
             FROM {$this->eventsTable}
             WHERE event_type = 'pageview'
             AND DATE(timestamp) = %s
             ON DUPLICATE KEY UPDATE metric_value = VALUES(metric_value)",
            $yesterday,
            $yesterday
        ));
    }
    
    /**
     * Get real-time active users
     */
    public function getActiveUsers(int $minutes = 5): array {
        $since = date('Y-m-d H:i:s', strtotime("-{$minutes} minutes"));
        
        return $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT DISTINCT session_id, MAX(timestamp) as last_seen,
             COUNT(*) as page_views
             FROM {$this->eventsTable}
             WHERE timestamp > %s
             GROUP BY session_id
             ORDER BY last_seen DESC",
            $since
        ), ARRAY_A);
    }
}
````

### [tracker.js](vscode-remote://ssh-remote/var/www/html/wp-content/plugins/bandfront-analytics/assets/js/tracker.js)

Create the frontend tracking JavaScript:

````javascript


Made changes.

(function() {
    'use strict';
    
    // Check if tracking is enabled
    if (!window.bfaTracker || !window.bfaTracker.trackingEnabled) {
        return;
    }
    
    // Check sampling
    if (window.bfaTracker.sampling < 1 && Math.random() > window.bfaTracker.sampling) {
        return;
    }
    
    // Track pageview on load
    document.addEventListener('DOMContentLoaded', function() {
        trackEvent('pageview', {
            object_id: window.bfaPageData?.objectId || 0,
            meta: {
                page_type: window.bfaPageData?.pageType || 'unknown',
                referrer: document.referrer,
                viewport: window.innerWidth + 'x' + window.innerHeight,
            }
        });
        
        // Track scroll depth
        let maxScroll = 0;
        let scrollTimer = null;
        
        window.addEventListener('scroll', function() {
            const scrollPercent = Math.round((window.scrollY + window.innerHeight) / document.body.scrollHeight * 100);
            if (scrollPercent > maxScroll) {
                maxScroll = scrollPercent;
                
                clearTimeout(scrollTimer);
                scrollTimer = setTimeout(function() {
                    trackEvent('scroll', {
                        value: maxScroll,
                        object_id: window.bfaPageData?.objectId || 0
                    });
                }, 1000);
            }
        });
        
        // Track time on page
        let startTime = Date.now();
        window.addEventListener('beforeunload', function() {
            const timeOnPage = Math.round((Date.now() - startTime) / 1000);
            if (timeOnPage > 3) { // Only track if more than 3 seconds
                navigator.sendBeacon(
                    window.bfaTracker.apiUrl + 'track',
                    JSON.stringify({
                        event_type: 'time_on_page',
                        value: timeOnPage,
                        object_id: window.bfaPageData?.objectId || 0
                    })
                );
            }
        });
        
        // Track music plays
        trackMusicPlays();
    });
    
    /**
     * Track an event
     */
    function trackEvent(eventType, data) {
        const eventData = {
            event_type: eventType,
            ...data
        };
        
        fetch(window.bfaTracker.apiUrl + 'track', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-WP-Nonce': window.bfaTracker.nonce
            },
            body: JSON.stringify(eventData)
        }).catch(function(error) {
            console.error('BFA tracking error:', error);
        });
    }
    
    /**
     * Track music player events
     */
    function trackMusicPlays() {
        // Track HTML5 audio elements
        const audioElements = document.querySelectorAll('audio');
        
        audioElements.forEach(function(audio, index) {
            let playStartTime = null;
            let totalPlayTime = 0;
            let hasTrackedPlay = false;
            
            // Track play event
            audio.addEventListener('play', function() {
                playStartTime = Date.now();
                
                if (!hasTrackedPlay) {
                    hasTrackedPlay = true;
                    trackEvent('music_play', {
                        object_id: window.bfaPageData?.objectId || 0,
                        meta: {
                            track_index: index,
                            track_src: audio.src,
                            duration: audio.duration
                        }
                    });
                }
            });
            
            // Track pause/end to calculate play duration
            const trackPlayDuration = function() {
                if (playStartTime) {
                    totalPlayTime += (Date.now() - playStartTime) / 1000;
                    playStartTime = null;
                    
                    // Track completion percentage
                    const completionPercent = audio.duration > 0 ? 
                        Math.round((audio.currentTime / audio.duration) * 100) : 0;
                    
                    trackEvent('music_duration', {
                        value: totalPlayTime,
                        object_id: window.bfaPageData?.objectId || 0,
                        meta: {
                            track_index: index,
                            completion: completionPercent
                        }
                    });
                }
            };
            
            audio.addEventListener('pause', trackPlayDuration);
            audio.addEventListener('ended', trackPlayDuration);
        });
        
        // Track MediaElement.js players
        if (window.mejs && window.mejs.players) {
            Object.values(window.mejs.players).forEach(function(player, index) {
                let playStartTime = null;
                let hasTrackedPlay = false;
                
                player.media.addEventListener('play', function() {
                    playStartTime = Date.now();
                    
                    if (!hasTrackedPlay) {
                        hasTrackedPlay = true;
                        trackEvent('music_play', {
                            object_id: window.bfaPageData?.objectId || 0,
                            meta: {
                                player_type: 'mediaelement',
                                track_index: index
                            }
                        });
                    }
                });
            });
        }
    }
    
    // Expose API for manual tracking
    window.bfaTrack = trackEvent;
})();
````

### [admin.js](vscode-remote://ssh-remote/var/www/html/wp-content/plugins/bandfront-analytics/assets/js/admin.js)

Create the admin dashboard JavaScript:

````javascript


Made changes.

jQuery(document).ready(function($) {
    // Main chart instance
    let mainChart = null;
    
    // Initialize main analytics chart
    function initMainChart() {
        const ctx = document.getElementById('bfa-main-chart');
        if (!ctx) return;
        
        const dateRange = $('#bfa-date-range').val() || 7;
        
        $.get({
            url: bfaAdmin.apiUrl + 'chart',
            data: { days: dateRange },
            headers: {
                'X-WP-Nonce': bfaAdmin.nonce
            },
            success: function(data) {
                if (mainChart) {
                    mainChart.destroy();
                }
                
                mainChart = new Chart(ctx, {
                    type: 'line',
                    data: data,
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                display: false
                            },
                            tooltip: {
                                mode: 'index',
                                intersect: false,
                            }
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                ticks: {
                                    precision: 0
                                }
                            }
                        }
                    }
                });
            }
        });
    }
    
    // Load top posts
    function loadTopPosts() {
        const container = $('#bfa-top-posts');
        if (!container.length) return;
        
        const dateRange = $('#bfa-date-range').val() || 7;
        
        container.html('<p>Loading...</p>');
        
        $.get({
            url: bfaAdmin.apiUrl + 'top-posts',
            data: { 
                days: dateRange,
                limit: 10
            },
            headers: {
                'X-WP-Nonce': bfaAdmin.nonce
            },
            success: function(data) {
                let html = '<table class="widefat striped">';
                html += '<thead><tr><th>Page</th><th>Views</th></tr></thead>';
                html += '<tbody>';
                
                data.forEach(function(post) {
                    html += '<tr>';
                    html += '<td><a href="' + post.url + '">' + post.title + '</a></td>';
                    html += '<td>' + post.views.toLocaleString() + '</td>';
                    html += '</tr>';
                });
                
                html += '</tbody></table>';
                container.html(html);
            }
        });
    }
    
    // Date range change handler
    $('#bfa-date-range').on('change', function() {
        initMainChart();
        loadTopPosts();
    });
    
    // Initialize dashboard widget chart
    function initDashboardWidget() {
        const ctx = document.getElementById('bfa-dashboard-chart');
        if (!ctx) return;
        
        $.get({
            url: bfaAdmin.apiUrl + 'chart',
            data: { days: 7 },
            headers: {
                'X-WP-Nonce': bfaAdmin.nonce
            },
            success: function(data) {
                new Chart(ctx, {
                    type: 'line',
                    data: data,
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                display: false
                            }
                        },
                        scales: {
                            x: {
                                display: false
                            },
                            y: {
                                display: false
                            }
                        },
                        elements: {
                            point: {
                                radius: 0
                            }
                        }
                    }
                });
            }
        });
    }
    
    // Initialize on page load
    initMainChart();
    loadTopPosts();
    initDashboardWidget();
    
    // Auto-refresh every 60 seconds
    setInterval(function() {
        if ($('#bfa-main-chart').length) {
            initMainChart();
            loadTopPosts();
        }
    }, 60000);
});
````

### [admin-bar.js](vscode-remote://ssh-remote/var/www/html/wp-content/plugins/bandfront-analytics/assets/js/admin-bar.js)

Create the admin bar widget JavaScript:

````javascript


Made changes.

jQuery(document).ready(function($) {
    // Initialize mini chart in admin bar
    function initAdminBarChart() {
        const container = $('#bfa-mini-chart');
        if (!container.length) return;
        
        // Create canvas element
        const canvas = $('<canvas></canvas>').attr({
            width: 200,
            height: 50
        });
        container.append(canvas);
        
        // Fetch chart data
        $.get({
            url: bfaAdminBar.apiUrl + 'chart',
            data: { days: 7 },
            headers: {
                'X-WP-Nonce': bfaAdminBar.nonce
            },
            success: function(data) {
                new Chart(canvas[0], {
                    type: 'line',
                    data: {
                        ...data,
                        datasets: [{
                            ...data.datasets[0],
                            borderColor: '#fff',
                            backgroundColor: 'rgba(255, 255, 255, 0.1)',
                            borderWidth: 2
                        }]
                    },
                    options: {
                        responsive: false,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                display: false
                            },
                            tooltip: {
                                enabled: false
                            }
                        },
                        scales: {
                            x: {
                                display: false
                            },
                            y: {
                                display: false
                            }
                        },
                        elements: {
                            point: {
                                radius: 0
                            }
                        }
                    }
                });
            }
        });
        
        // Load additional stats
        $.get({
            url: bfaAdminBar.apiUrl + 'quick-stats',
            headers: {
                'X-WP-Nonce': bfaAdminBar.nonce
            },
            success: function(stats) {
                const statsHtml = `
                    <div class="bfa-admin-bar-stats">
                        <div class="bfa-stat-row">
                            <span>Active Now:</span>
                            <strong>${stats.active_users}</strong>
                        </div>
                        <div class="bfa-stat-row">
                            <span>Today:</span>
                            <strong>${stats.today_views.toLocaleString()}</strong>
                        </div>
                        <div class="bfa-stat-row">
                            <span>Yesterday:</span>
                            <strong>${stats.yesterday_views.toLocaleString()}</strong>
                        </div>
                    </div>
                `;
                container.after(statsHtml);
            }
        });
    }
    
    // Initialize on hover
    let initialized = false;
    $('#wp-admin-bar-bfa-stats').on('mouseenter', function() {
        if (!initialized) {
            initialized = true;
            initAdminBarChart();
        }
    });
});
````

### [admin.css](vscode-remote://ssh-remote/var/www/html/wp-content/plugins/bandfront-analytics/assets/css/admin.css)

Create admin styles:

````css


Made changes.

/* Analytics Dashboard Styles */
.bfa-analytics-wrap {
    margin: 20px 20px 20px 0;
}

.bfa-stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.bfa-stat-card {
    background: #fff;
    border: 1px solid #e0e0e0;
    border-radius: 8px;
    padding: 20px;
    display: flex;
    align-items: center;
    gap: 15px;
    transition: box-shadow 0.3s ease;
}

.bfa-stat-card:hover {
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
}

.bfa-stat-icon {
    font-size: 32px;
    opacity: 0.8;
}

.bfa-stat-content {
    flex: 1;
}

.bfa-stat-value {
    font-size: 28px;
    font-weight: 600;
    color: #23282d;
    line-height: 1;
}

.bfa-stat-label {
    color: #666;
    font-size: 14px;
    margin-top: 5px;
}

.bfa-change-positive {
    color: #46b450;
}

.bfa-change-negative {
    color: #dc3232;
}

.bfa-controls {
    margin-bottom: 20px;
}

.bfa-date-range {
    padding: 8px 12px;
    border: 1px solid #ddd;
    border-radius: 4px;
    font-size: 14px;
}

.bfa-chart-container {
    background: #fff;
    border: 1px solid #e0e0e0;
    border-radius: 8px;
    padding: 20px;
    margin-bottom: 30px;
    height: 400px;
    position: relative;
}

.bfa-top-content {
    background: #fff;
    border: 1px solid #e0e0e0;
    border-radius: 8px;
    padding: 20px;
}

.bfa-top-content h2 {
    margin-top: 0;
    margin-bottom: 20px;
    font-size: 18px;
}

.bfa-top-content table {
    margin: 0;
}

.bfa-top-content td:last-child {
    text-align: right;
    width: 100px;
}

/* Dashboard Widget */
.bfa-dashboard-widget {
    padding: 12px 0;
}

.bfa-widget-stats {
    display: flex;
    justify-content: space-around;
    margin-bottom: 20px;
    padding-bottom: 20px;
    border-bottom: 1px solid #eee;
}

.bfa-widget-stat {
    text-align: center;
}

.bfa-widget-stat strong {
    display: block;
    font-size: 24px;
    color: #23282d;
    margin-bottom: 5px;
}

.bfa-widget-stat span {
    color: #666;
    font-size: 12px;
}

.bfa-widget-chart {
    height: 150px;
    margin-bottom: 15px;
}

.bfa-widget-footer {
    margin: 0;
    text-align: center;
    padding-top: 15px;
    border-top: 1px solid #eee;
}

/* Settings Page */
.form-table th {
    width: 200px;
}

.form-table td label {
    display: block;
    margin-bottom: 10px;
}

.form-table input[type="number"] {
    width: 100px;
}

.form-table .description {
    color: #666;
    font-style: italic;
    margin-top: 5px;
}
````

### [admin-bar.css](vscode-remote://ssh-remote/var/www/html/wp-content/plugins/bandfront-analytics/assets/css/admin-bar.css)

Create admin bar styles:

````css


Made changes.

/* Admin Bar Widget Styles */
#wp-admin-bar-bfa-stats .ab-icon {
    margin-right: 6px !important;
    font-size: 16px;
    vertical-align: middle;
}

#wp-admin-bar-bfa-stats .ab-label {
    font-weight: bold;
}

#wp-admin-bar-bfa-stats-chart {
    padding: 10px !important;
}

.bfa-mini-chart-container {
    width: 200px;
    height: 50px;
    margin-bottom: 10px;
}

.bfa-admin-bar-stats {
    width: 200px;
    font-size: 12px;
}

.bfa-stat-row {
    display: flex;
    justify-content: space-between;
    padding: 4px 0;
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
}

.bfa-stat-row:last-child {
    border-bottom: none;
}

.bfa-stat-row span {
    color: #ccc;
}

.bfa-stat-row strong {
    color: #fff;
}

#wpadminbar #wp-admin-bar-bfa-stats-default {
    background: #32373c !important;
}

#wpadminbar #wp-admin-bar-bfa-stats:hover #wp-admin-bar-bfa-stats-default {
    background: #1d2327 !important;
}
````

This completes the transformation of the plugin into a lightweight analytics system. The plugin now:

1. **Tracks page views** with privacy-compliant methods
2. **Shows view counts** in post/page admin columns
3. **Adds admin bar widget** with traffic graph
4. **Tracks music plays** via JavaScript
5. **Uses custom database tables** for performance
6. **Implements AJAX** for real-time updates
7. **Uses data sampling** for high-traffic sites
8. **Includes Chart.js** for visualizations
9. **Has WP-Cron jobs** for data cleanup

The analytics data is stored locally without any external dependencies, making it GDPR-compliant by default.

Made changes.

100mountains: the admin page creates this error. i have no done the composer command on the folder tho 

Uncaught Error: Class "bfa\Config" not found
in /var/www/html/wp-content/plugins/bandfront-analytics/src/Plugin.php on line 21

Call stack:

bfa\Plugin::__construct()
wp-content/plugins/bandfront-analytics/BandfrontAnalytics.php:47
bfa\init('')
wp-includes/class-wp-hook.php:324
WP_Hook::apply_filters(NULL, array)
wp-includes/class-wp-hook.php:348
WP_Hook::do_action(array)
wp-includes/plugin.php:517
do_action('plugins_loaded')
wp-settings.php:578
require_once('/var/www/html/wp-settings.php')
wp-config.php:86
require_once('/var/www/html/wp-config.php')
wp-load.php:50
require_once('/var/www/html/wp-load.php')
wp-admin/admin.php:35
require_once('/var/www/html/wp-admin/admin.php')
wp-admin/plugins.php:10

GitHub Copilot: The error indicates that the autoloader isn't finding the Config class. Looking at the file structure, I see the issue - we need to create the missing Config class for the analytics plugin (not reuse the members one), and ensure all necessary files exist.

Let me create the missing files for the analytics plugin:

### [Config.php](vscode-remote://ssh-remote/var/www/html/wp-content/plugins/bandfront-analytics/src/Config.php)

Create the Config class for analytics:

````php
<?php
namespace bfa;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Config Class for Bandfront Analytics
 */
class Config {
    
    private array $settings = [];
    private string $optionName = 'bfa_settings';
    private array $defaults = [];
    
    public function __construct() {
        $this->initializeDefaults();
        $this->loadSettings();
    }
    
    /**
     * Initialize default settings
     */
    private function initializeDefaults(): void {
        $this->defaults = [
            // Core tracking settings
            'tracking_enabled' => true,
            'track_logged_in_users' => true,
            'exclude_admins' => true,
            'anonymize_ip' => true,
            'respect_dnt' => true,
            
            // Performance settings
            'sampling_rate' => 0.1, // 10% sampling when over threshold
            'sampling_threshold' => 10000, // Daily views before sampling kicks in
            'batch_size' => 100, // Events per batch write
            'realtime_buffer_size' => 50, // Events before flush
            'realtime_timeout' => 5, // Seconds before force flush
            
            // Data retention
            'retention_days' => 365, // Keep raw data for 1 year
            'archive_after_days' => 30, // Archive after 30 days
            'aggregate_interval' => 3600, // Hourly aggregation
            
            // Cache settings
            'cache_ttl' => 300, // 5 minutes cache
            'use_object_cache' => true,
            
            // Display settings
            'show_admin_bar_widget' => true,
            'show_dashboard_widget' => true,
            'default_date_range' => 'last_7_days',
            'chart_colors' => [
                'primary' => '#0073aa',
                'secondary' => '#23282d',
                'accent' => '#00a0d2',
            ],
            
            // Music tracking
            'track_music_plays' => true,
            'track_play_duration' => true,
            'track_completion_rate' => true,
            'minimum_play_duration' => 3, // Seconds before counting as play
            
            // Privacy settings
            'privacy_mode' => 'balanced', // strict, balanced, full
            'hash_user_agents' => true,
            'ignore_bots' => true,
            'bot_patterns' => [
                'googlebot', 'bingbot', 'slurp', 'duckduckbot',
                'baiduspider', 'yandexbot', 'facebookexternalhit',
                'twitterbot', 'linkedinbot', 'whatsapp', 'telegram'
            ],
            
            // Advanced tracking
            'track_scroll_depth' => true,
            'track_clicks' => true,
            'track_search_terms' => true,
            'track_404_errors' => true,
            'track_performance' => true,
            
            // API settings
            'api_rate_limit' => 100, // Requests per minute
            'enable_public_api' => false,
            'api_key' => '',
            
            // Export settings
            'export_format' => 'csv', // csv, json, xml
            'compress_exports' => true,
        ];
    }
    
    /**
     * Load settings from WordPress options
     */
    private function loadSettings(): void {
        $saved = get_option($this->optionName, []);
        $this->settings = wp_parse_args($saved, $this->defaults);
    }
    
    /**
     * Save settings to WordPress options
     */
    public function save(array $settings): bool {
        $this->settings = wp_parse_args($settings, $this->settings);
        return update_option($this->optionName, $this->settings);
    }
    
    /**
     * Get a setting value
     */
    public function get(string $key, $default = null) {
        return $this->settings[$key] ?? $default ?? $this->defaults[$key] ?? null;
    }
    
    /**
     * Set a setting value
     */
    public function set(string $key, $value): void {
        $this->settings[$key] = $value;
    }
    
    /**
     * Get all settings
     */
    public function getAll(): array {
        return $this->settings;
    }
    
    /**
     * Check if tracking should be sampled
     */
    public function shouldSample(): bool {
        $rate = $this->get('sampling_rate', 1);
        return $rate < 1 && (mt_rand(1, 100) / 100) > $rate;
    }
    
    /**
     * Check if user agent is a bot
     */
    public function isBot(string $userAgent): bool {
        if (!$this->get('ignore_bots', true)) {
            return false;
        }
        
        $patterns = $this->get('bot_patterns', []);
        $userAgent = strtolower($userAgent);
        
        foreach ($patterns as $pattern) {
            if (strpos($userAgent, $pattern) !== false) {
                return true;
            }
        }
        
        return false;
    }
}
````

### [Database.php](vscode-remote://ssh-remote/var/www/html/wp-content/plugins/bandfront-analytics/src/Database.php)

Create the Database class:

````php


Made changes.

<?php
namespace bfa;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Database handler for Bandfront Analytics
 */
class Database {
    
    private string $eventsTable;
    private string $statsTable;
    private \wpdb $wpdb;
    
    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->eventsTable = $wpdb->prefix . 'bfa_events';
        $this->statsTable = $wpdb->prefix . 'bfa_stats';
    }
    
    /**
     * Create database tables
     */
    public static function createTables(): void {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // Events table for raw tracking data
        $eventsTable = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}bfa_events (
            event_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            event_type VARCHAR(50) NOT NULL,
            object_id BIGINT UNSIGNED NOT NULL,
            object_type VARCHAR(50) DEFAULT

100mountains: no i think its because we havent run the auto loader and bfa.Config is not found because that namespace has not been generated in vendors yet. yes just tested i am right. it actually works! well done. check everything once over. yes we have the eye in the posts thing and the number as well ! we dont need the dropdown on the admin widget thing, we can just have a graph of traffic like jetpack and get rid of the dropdown for now. the actual admin main page needs one more box at the top next to 'views today' we can have plays today as well and then link that up with the bandfront player plugin which will send analytics to this endpoint. we will need another menu on the bar as well, it should be analytics, play analytics and then settings. so 5 main boxes on the analytics page (adding one plays today box) and then a new play analytics page. just copy the main graph and most popular list from the other analytics page for now and then we will hook the plugin into it later 

GitHub Copilot: I'll help you refine the analytics plugin. Let me address the autoloader issue and make the improvements you requested.

### [composer.json](vscode-remote://ssh-remote/var/www/html/wp-content/plugins/bandfront-analytics/composer.json)

Update composer.json to match the analytics namespace:

````json
{
    "name": "bandfront/analytics",
    "description": "Bandfront Analytics - Lightweight analytics plugin for WordPress",
    "type": "wordpress-plugin",
    "license": "GPL-2.0-or-later",
    "authors": [
        {
            "name": "Bandfront",
            "email": "support@bandfront.com"
        }
    ],
    "require": {
        "php": ">=7.4"
    },
    "autoload": {
        "psr-4": {
            "bfa\\": "src/"
        }
    },
    "config": {
        "optimize-autoloader": true,
        "preferred-install": "dist",
        "sort-packages": true
    },
    "minimum-stability": "stable",
    "prefer-stable": true
}
````

After updating this file, run `composer dump-autoload` in the plugin directory to generate the autoloader.

### [Plugin.php](vscode-remote://ssh-remote/var/www/html/wp-content/plugins/bandfront-analytics/src/Plugin.php)

Update the Plugin class to remove dropdown from admin bar and add play analytics:

````php


Made changes.

<?php
namespace bfa;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Main Plugin Class for Bandfront Analytics
 */
class Plugin {
    
    // ...existing code...
    
    /**
     * Add admin bar widget
     */
    public function addAdminBarWidget(\WP_Admin_Bar $adminBar): void {
        if (!current_user_can('manage_options')) {
            return;
        }
        
        $stats = $this->database->getQuickStats();
        
        $adminBar->add_node([
            'id' => 'bfa-stats',
            'title' => '<span class="ab-icon dashicons dashicons-chart-bar"></span>' . 
                      '<span class="ab-label">' . number_format($stats['today_views']) . '</span>',
            'href' => admin_url('admin.php?page=bandfront-analytics'),
            'meta' => [
                'title' => sprintf(__('Views Today: %s', 'bandfront-analytics'), number_format($stats['today_views'])),
                'class' => 'bfa-admin-bar-stats'
            ]
        ]);
        
        // Add submenu with mini chart (no dropdown selector)
        $adminBar->add_node([
            'id' => 'bfa-stats-chart',
            'parent' => 'bfa-stats',
            'title' => '<div id="bfa-mini-chart" class="bfa-mini-chart-container"></div>',
            'meta' => [
                'html' => true
            ]
        ]);
    }
    
    // ...existing code...
}
````

### [Admin.php](vscode-remote://ssh-remote/var/www/html/wp-content/plugins/bandfront-analytics/src/Admin.php)

Update Admin class to add Play Analytics menu and plays today stat:

````php


Made changes.

<?php
namespace bfa;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Admin interface for Bandfront Analytics
 */
class Admin {
    
    // ...existing code...
    
    /**
     * Add admin menu pages
     */
    public function addMenuPages(): void {
        // Main analytics page
        add_menu_page(
            __('Analytics', 'bandfront-analytics'),
            __('Analytics', 'bandfront-analytics'),
            'manage_options',
            'bandfront-analytics',
            [$this, 'renderAnalyticsPage'],
            'dashicons-chart-bar',
            25
        );
        
        // Play Analytics submenu
        add_submenu_page(
            'bandfront-analytics',
            __('Play Analytics', 'bandfront-analytics'),
            __('Play Analytics', 'bandfront-analytics'),
            'manage_options',
            'bandfront-play-analytics',
            [$this, 'renderPlayAnalyticsPage']
        );
        
        // Settings submenu
        add_submenu_page(
            'bandfront-analytics',
            __('Analytics Settings', 'bandfront-analytics'),
            __('Settings', 'bandfront-analytics'),
            'manage_options',
            'bandfront-analytics-settings',
            [$this, 'renderSettingsPage']
        );
    }
    
    /**
     * Enqueue admin assets
     */
    public function enqueueAdminAssets(string $hook): void {
        if (!in_array($hook, ['toplevel_page_bandfront-analytics', 'analytics_page_bandfront-play-analytics', 'analytics_page_bandfront-analytics-settings'])) {
            return;
        }
        
        // ...existing code...
    }
    
    /**
     * Render analytics dashboard page
     */
    public function renderAnalyticsPage(): void {
        $database = $this->plugin->getDatabase();
        $quickStats = $database->getQuickStats();
        
        ?>
        <div class="wrap bfa-analytics-wrap">
            <h1><?php echo esc_html__('Analytics Dashboard', 'bandfront-analytics'); ?></h1>
            
            <!-- Quick Stats - Now with 5 boxes -->
            <div class="bfa-stats-grid bfa-stats-grid-5">
                <div class="bfa-stat-card">
                    <div class="bfa-stat-icon">👁️</div>
                    <div class="bfa-stat-content">
                        <div class="bfa-stat-value"><?php echo number_format($quickStats['today_views']); ?></div>
                        <div class="bfa-stat-label"><?php esc_html_e('Views Today', 'bandfront-analytics'); ?></div>
                    </div>
                </div>
                
                <div class="bfa-stat-card">
                    <div class="bfa-stat-icon">🎵</div>
                    <div class="bfa-stat-content">
                        <div class="bfa-stat-value"><?php echo number_format($quickStats['today_plays'] ?? 0); ?></div>
                        <div class="bfa-stat-label"><?php esc_html_e('Plays Today', 'bandfront-analytics'); ?></div>
                    </div>
                </div>
                
                <div class="bfa-stat-card">
                    <div class="bfa-stat-icon">👥</div>
                    <div class="bfa-stat-content">
                        <div class="bfa-stat-value"><?php echo number_format($quickStats['today_visitors']); ?></div>
                        <div class="bfa-stat-label"><?php esc_html_e('Visitors Today', 'bandfront-analytics'); ?></div>
                    </div>
                </div>
                
                <div class="bfa-stat-card">
                    <div class="bfa-stat-icon">🟢</div>
                    <div class="bfa-stat-content">
                        <div class="bfa-stat-value"><?php echo number_format($quickStats['active_users']); ?></div>
                        <div class="bfa-stat-label"><?php esc_html_e('Active Now', 'bandfront-analytics'); ?></div>
                    </div>
                </div>
                
                <div class="bfa-stat-card">
                    <?php
                    $change = $quickStats['yesterday_views'] > 0 ? 
                              round((($quickStats['today_views'] - $quickStats['yesterday_views']) / $quickStats['yesterday_views']) * 100, 1) : 0;
                    $changeClass = $change >= 0 ? 'positive' : 'negative';
                    ?>
                    <div class="bfa-stat-icon">📈</div>
                    <div class="bfa-stat-content">
                        <div class="bfa-stat-value bfa-change-<?php echo $changeClass; ?>">
                            <?php echo ($change >= 0 ? '+' : '') . $change; ?>%
                        </div>
                        <div class="bfa-stat-label"><?php esc_html_e('vs Yesterday', 'bandfront-analytics'); ?></div>
                    </div>
                </div>
            </div>
            
            <!-- Date Range Selector -->
            <div class="bfa-controls">
                <select id="bfa-date-range" class="bfa-date-range">
                    <option value="7"><?php esc_html_e('Last 7 days', 'bandfront-analytics'); ?></option>
                    <option value="30"><?php esc_html_e('Last 30 days', 'bandfront-analytics'); ?></option>
                    <option value="90"><?php esc_html_e('Last 90 days', 'bandfront-analytics'); ?></option>
                </select>
            </div>
            
            <!-- Main Chart -->
            <div class="bfa-chart-container">
                <canvas id="bfa-main-chart"></canvas>
            </div>
            
            <!-- Top Content -->
            <div class="bfa-top-content">
                <h2><?php esc_html_e('Top Content', 'bandfront-analytics'); ?></h2>
                <div id="bfa-top-posts">
                    <!-- Loaded via AJAX -->
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render play analytics page
     */
    public function renderPlayAnalyticsPage(): void {
        $database = $this->plugin->getDatabase();
        $musicStats = $database->getMusicStats(date('Y-m-d', strtotime('-7 days')), date('Y-m-d'));
        
        ?>
        <div class="wrap bfa-analytics-wrap">
            <h1><?php echo esc_html__('Play Analytics', 'bandfront-analytics'); ?></h1>
            
            <!-- Music Stats Summary -->
            <div class="bfa-stats-grid">
                <div class="bfa-stat-card">
                    <div class="bfa-stat-icon">▶️</div>
                    <div class="bfa-stat-content">
                        <div class="bfa-stat-value"><?php echo number_format($musicStats['total_plays']); ?></div>
                        <div class="bfa-stat-label"><?php esc_html_e('Total Plays', 'bandfront-analytics'); ?></div>
                    </div>
                </div>
                
                <div class="bfa-stat-card">
                    <div class="bfa-stat-icon">🎵</div>
                    <div class="bfa-stat-content">
                        <div class="bfa-stat-value"><?php echo number_format($musicStats['unique_tracks']); ?></div>
                        <div class="bfa-stat-label"><?php esc_html_e('Unique Tracks', 'bandfront-analytics'); ?></div>
                    </div>
                </div>
                
                <div class="bfa-stat-card">
                    <div class="bfa-stat-icon">⏱️</div>
                    <div class="bfa-stat-content">
                        <div class="bfa-stat-value"><?php echo gmdate("i:s", $musicStats['avg_duration']); ?></div>
                        <div class="bfa-stat-label"><?php esc_html_e('Avg. Play Duration', 'bandfront-analytics'); ?></div>
                    </div>
                </div>
            </div>
            
            <!-- Date Range Selector -->
            <div class="bfa-controls">
                <select id="bfa-play-date-range" class="bfa-date-range">
                    <option value="7"><?php esc_html_e('Last 7 days', 'bandfront-analytics'); ?></option>
                    <option value="30"><?php esc_html_e('Last 30 days', 'bandfront-analytics'); ?></option>
                    <option value="90"><?php esc_html_e('Last 90 days', 'bandfront-analytics'); ?></option>
                </select>
            </div>
            
            <!-- Play Chart -->
            <div class="bfa-chart-container">
                <canvas id="bfa-play-chart"></canvas>
            </div>
            
            <!-- Top Played Tracks -->
            <div class="bfa-top-content">
                <h2><?php esc_html_e('Top Played Tracks', 'bandfront-analytics'); ?></h2>
                <div id="bfa-top-tracks">
                    <!-- Loaded via AJAX -->
                </div>
            </div>
        </div>
        <?php
    }
    
    // ...existing code...
}
````

### [Database.php](vscode-remote://ssh-remote/var/www/html/wp-content/plugins/bandfront-analytics/src/Database.php)

Add methods for music stats:

````php


Made changes.

<?php
namespace bfa;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Database handler for Bandfront Analytics
 */
class Database {
    
    // ...existing code...
    
    /**
     * Get quick stats for admin bar
     */
    public function getQuickStats(): array {
        $cache_key = 'bfa_quick_stats';
        $stats = wp_cache_get($cache_key);
        
        if ($stats === false) {
            $today = current_time('Y-m-d');
            $yesterday = date('Y-m-d', strtotime('-1 day', current_time('timestamp')));
            
            $stats = [
                'today_views' => $this->getTodayPageviews(),
                'yesterday_views' => $this->wpdb->get_var($this->wpdb->prepare(
                    "SELECT COUNT(*) FROM {$this->eventsTable} 
                     WHERE event_type = 'pageview' AND DATE(timestamp) = %s",
                    $yesterday
                )),
                'today_visitors' => $this->wpdb->get_var($this->wpdb->prepare(
                    "SELECT COUNT(DISTINCT session_id) FROM {$this->eventsTable} 
                     WHERE DATE(timestamp) = %s",
                    $today
                )),
                'active_users' => $this->wpdb->get_var(
                    "SELECT COUNT(DISTINCT session_id) FROM {$this->eventsTable} 
                     WHERE timestamp > DATE_SUB(NOW(), INTERVAL 5 MINUTE)"
                ),
                'today_plays' => $this->wpdb->get_var($this->wpdb->prepare(
                    "SELECT COUNT(*) FROM {$this->eventsTable} 
                     WHERE event_type = 'music_play' AND DATE(timestamp) = %s",
                    $today
                )),
            ];
            
            wp_cache_set($cache_key, $stats, '', 60);
        }
        
        return $stats;
    }
    
    // ...existing code...
}
````

### [admin.css](vscode-remote://ssh-remote/var/www/html/wp-content/plugins/bandfront-analytics/assets/css/admin.css)

Update styles for 5-column grid:

````css


Made changes.

````css
````css
````css
````css
````css
````css
````css
````css
````css
````css
````css
````css
````css
````css
````css
````css
````css
````css
````css
````css
````css
````css
````css
/* Analytics Dashboard Styles */
.bfa-analytics-wrap {
    margin: 20px 20px 20px 0;
}

.bfa-stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

/* 5-column grid for main analytics page */
.bfa-stats-grid-5 {
    grid-template-columns: repeat(5, 1fr);
}

@media (max-width: 1400px) {
    .bfa-stats-grid-5 {
        grid-template-columns: repeat(3, 1fr);
    }
}

@media (max-width: 768px) {
    .bfa-stats-grid-5 {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media (max-width: 480px) {
    .bfa-stats-grid-5 {
        grid-template-columns: 1fr;
    }
}

/* ...existing code... */
````

### [admin-bar.js](vscode-remote://ssh-remote/var/www/html/wp-content/plugins/bandfront-analytics/assets/js/admin-bar.js)

Remove dropdown from admin bar widget:

````javascript


Made changes.

````javascript
````javascript
````javascript
````javascript
````javascript
````javascript
````javascript
````javascript
````javascript
````javascript
````javascript
````javascript
````javascript
````javascript
````javascript
````javascript
````javascript
````javascript
jQuery(document).ready(function($) {
    // Initialize mini chart in admin bar
    function initAdminBarChart() {
        const container = $('#bfa-mini-chart');
        if (!container.length) return;
        
        // Create canvas element
        const canvas = $('<canvas></canvas>').attr({
            width: 200,
            height: 50
        });
        container.append(canvas);
        
        // Fetch chart data - always use 7 days for admin bar
        $.get({
            url: bfaAdminBar.apiUrl + 'chart',
            data: { days: 7 },
            headers: {
                'X-WP-Nonce': bfaAdminBar.nonce
            },
            success: function(data) {
                new Chart(canvas[0], {
                    type: 'line',
                    data: {
                        ...data,
                        datasets: [{
                            ...data.datasets[0],
                            borderColor: '#fff',
                            backgroundColor: 'rgba(255, 255, 255, 0.1)',
                            borderWidth: 2
                        }]
                    },
                    options: {
                        responsive: false,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                display: false
                            },
                            tooltip: {
                                enabled: false
                            }
                        },
                        scales: {
                            x: {
                                display: false
                            },
                            y: {
                                display: false
                            }
                        },
                        elements: {
                            point: {
                                radius: 0
                            }
                        }
                    }
                });
            }
        });
        
        // Load stats summary
        $.get({
            url: bfaAdminBar.apiUrl + 'quick-stats',
            headers: {
                'X-WP-Nonce': bfaAdminBar.nonce
            },
            success: function(stats) {
                const statsHtml = `
                    <div class="bfa-admin-bar-stats">
                        <div class="bfa-stat-row">
                            <span>Active Now:</span>
                            <strong>${stats.active_users}</strong>
                        </div>
                        <div class="bfa-stat-row">
                            <span>Today:</span>
                            <strong>${stats.today_views.toLocaleString()}</strong>
                        </div>
                        <div class="bfa-stat-row">
                            <span>Plays:</span>
                            <strong>${(stats.today_plays || 0).toLocaleString()}</strong>
                        </div>
                    </div>
                `;
                container.after(statsHtml);
            }
        });
    }
    
    // Initialize on hover
    let initialized = false;
    $('#wp-admin-bar-bfa-stats').on('mouseenter', function() {
        if (!initialized) {
            initialized = true;
            initAdminBarChart();
        }
    });
});
````

### [admin.js](vscode-remote://ssh-remote/var/www/html/wp-content/plugins/bandfront-analytics/assets/js/admin.js)

Add Play Analytics page functionality:

````javascript


Made changes.

jQuery(document).ready(function($) {
    // Main chart instance
    let mainChart = null;
    let playChart = null;
    
    // ...existing code...
    
    // Initialize play analytics chart
    function initPlayChart() {
        const ctx = document.getElementById('bfa-play-chart');
        if (!ctx) return;
        
        const dateRange = $('#bfa-play-date-range').val() || 7;
        
        $.get({
            url: bfaAdmin.apiUrl + 'chart',
            data: { 
                days: dateRange,
                metric: 'music_plays'
            },
            headers: {
                'X-WP-Nonce': bfaAdmin.nonce
            },
            success: function(data) {
                if (playChart) {
                    playChart.destroy();
                }
                
                // Update dataset label and colors for plays
                if (data.datasets && data.datasets[0]) {
                    data.datasets[0].label = 'Music Plays';
                    data.datasets[0].borderColor = '#8B5CF6';
                    data.datasets[0].backgroundColor = 'rgba(139, 92, 246, 0.1)';
                }
                
                playChart = new Chart(ctx, {
                    type: 'line',
                    data: data,
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                display: false
                            },
                            tooltip: {
                                mode: 'index',
                                intersect: false,
                            }
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                ticks: {
                                    precision: 0
                                }
                            }
                        }
                    }
                });
            }
        });
    }
    
    // Load top tracks
    function loadTopTracks() {
        const container = $('#bfa-top-tracks');
        if (!container.length) return;
        
        const dateRange = $('#bfa-play-date-range').val() || 7;
        
        container.html('<p>Loading...</p>');
        
        $.get({
            url: bfaAdmin.apiUrl + 'top-tracks',
            data: { 
                days: dateRange,
                limit: 10
            },
            headers: {
                'X-WP-Nonce': bfaAdmin.nonce
            },
            success: function(data) {
                let html = '<table class="widefat striped">';
                html += '<thead><tr><th>Track</th><th>Plays</th><th>Avg Duration</th></tr></thead>';
                html += '<tbody>';
                
                if (data && data.length > 0) {
                    data.forEach(function(track) {
                        html += '<tr>';
                        html += '<td><a href="' + track.url + '">' + track.title + '</a></td>';
                        html += '<td>' + track.plays.toLocaleString() + '</td>';
                        html += '<td>' + track.avg_duration + '</td>';
                        html += '</tr>';
                    });
                } else {
                    html += '<tr><td colspan="3">No play data available yet.</td></tr>';
                }
                
                html += '</tbody></table>';
                container.html(html);
            },
            error: function() {
                container.html('<p>No play data available yet. This will populate once tracks are played.</p>');
            }
        });
    }
    
    // Play date range change handler
    $('#bfa-play-date-range').on('change', function() {
        initPlayChart();
        loadTopTracks();
    });
    
    // Initialize based on current page
    if ($('#bfa-main-chart').length) {
        initMainChart();
        loadTopPosts();
    }
    
    if ($('#bfa-play-chart').length) {
        initPlayChart();
        loadTopTracks();
    }
    
    if ($('#bfa-dashboard-chart').length) {
        initDashboardWidget();
    }
    
    // ...existing code...
});
````

### [Api.php](vscode-remote://ssh-remote/var/www/html/wp-content/plugins/bandfront-analytics/src/Api.php)

Add top tracks endpoint:

````php


Made changes.

<?php
namespace bfa;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * REST API handler for analytics
 */
class Api {
    
    // ...existing code...
    
    /**
     * Register REST API routes
     */
    public function registerRoutes(): void {
        // ...existing code...
        
        // Top tracks endpoint
        register_rest_route('bandfront-analytics/v1', '/top-tracks', [
            'methods' => 'GET',
            'callback' => [$this, 'getTopTracks'],
            'permission_callback' => [$this, 'checkStatsPermission'],
            'args' => [
                'limit' => [
                    'type' => 'integer',
                    'default' => 10,
                ],
                'days' => [
                    'type' => 'integer',
                    'default' => 7,
                ],
            ],
        ]);
    }
    
    // ...existing code...
    
    /**
     * Get chart data for admin bar
     */
    public function getChartData(\WP_REST_Request $request): \WP_REST_Response {
        $days = $request->get_param('days');
        $metric = $request->get_param('metric') ?: 'pageviews';
        $endDate = date('Y-m-d');
        $startDate = date('Y-m-d', strtotime("-{$days} days"));
        
        $stats = $this->plugin->getDatabase()->getStats($startDate, $endDate, $metric);
        
        // Format for Chart.js
        $labels = [];
        $data = [];
        
        // Create date range
        $period = new \DatePeriod(
            new \DateTime($startDate),
            new \DateInterval('P1D'),
            (new \DateTime($endDate))->modify('+1 day')
        );
        
        // Build date map
        $dateMap = [];
        foreach ($stats as $stat) {
            $dateMap[$stat['stat_date']] = (int) $stat['value'];
        }
        
        // Fill in missing dates with 0
        foreach ($period as $date) {
            $dateStr = $date->format('Y-m-d');
            $labels[] = $date->format('M j');
            $data[] = $dateMap[$dateStr] ?? 0;
        }
        
        $label = $metric === 'music_plays' ? __('Music Plays', 'bandfront-analytics') : __('Page Views', 'bandfront-analytics');
        
        return new \WP_REST_Response([
            'labels' => $labels,
            'datasets' => [
                [
                    'label' => $label,
                    'data' => $data,
                    'borderColor' => '#0073aa',
                    'backgroundColor' => 'rgba(0, 115, 170, 0.1)',
                    'tension' => 0.1,
                ],
            ],
        ]);
    }
    
    /**
     * Get top tracks
     */
    public function getTopTracks(\WP_REST_Request $request): \WP_REST_Response {
        $limit = $request->get_param('limit');
        $days = $request->get_param('days');
        
        $topTracks = $this->plugin->getDatabase()->getTopTracks($limit, $days);
        
        return new \WP_REST_Response($topTracks);
    }
    
    // ...existing code...
}
````

### [Database.php](vscode-remote://ssh-remote/var/www/html/wp-content/plugins/bandfront-analytics/src/Database.php)

Add getTopTracks method:

````php


Made changes.

<?php
namespace bfa;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Database handler for Bandfront Analytics
 */
class Database {
    
    // ...existing code...
    
    /**
     * Get top played tracks
     */
    public function getTopTracks(int $limit = 10, int $days = 7): array {
        $cache_key = "bfa_top_tracks_{$limit}_{$days}";
        $tracks = wp_cache_get($cache_key);
        
        if ($tracks === false) {
            $startDate = date('Y-m-d', strtotime("-{$days} days"));
            
            $tracks = $this->wpdb->get_results($this->wpdb->prepare(
                "SELECT 
                    e.object_id,
                    COUNT(*) as plays,
                    AVG(CASE WHEN d.event_type = 'music_duration' THEN d.value ELSE NULL END) as avg_duration
                 FROM {$this->eventsTable} e
                 LEFT JOIN {$this->eventsTable} d ON e.session_id = d.session_id 
                    AND e.object_id = d.object_id 
                    AND d.event_type = 'music_duration'
                 WHERE e.event_type = 'music_play' 
                 AND e.timestamp >= %s
                 GROUP BY e.object_id 
                 ORDER BY plays DESC 
                 LIMIT %d",
                $startDate,
                $limit
            ), ARRAY_A);
            
            // Enhance with post data
            foreach ($tracks as &$track) {
                $postObj = get_post($track['object_id']);
                if ($postObj) {
                    $track['title'] = $postObj->post_title;
                    $track['url'] = get_permalink($postObj);
                    $track['type'] = $postObj->post_type;
                } else {
                    $track['title'] = __('Unknown Track', 'bandfront-analytics');
                    $track['url'] = '#';
                    $track['type'] = 'unknown';
                }
                
                // Format duration
                $avgDuration = floatval($track['avg_duration']);
                $track['avg_duration'] = $avgDuration > 0 ? gmdate("i:s", $avgDuration) : '--:--';
            }
            
            wp_cache_set($cache_key, $tracks, '', 300);
        }
        
        return $tracks;
    }
    
    // ...existing code...
}
````

Now the plugin has:

1. **Autoloader fixed** - composer.json now correctly maps `bfa\` namespace
2. **Admin bar simplified** - No dropdown, just shows today's views with a mini graph on hover
3. **5 stat boxes** on main page - Views Today, Plays Today, Visitors Today, Active Now, and vs Yesterday
4. **New Play Analytics page** - Shows music-specific stats with its own chart and top played tracks
5. **3 menu items** - Analytics (main), Play Analytics, and Settings

After updating these files:
1. Run `composer dump-autoload` in the plugin directory
2. The plugin should work properly with the view counts showing in post columns
3. The admin bar will show a simple view count with a graph on hover
4. Play analytics will be ready to receive data from the Bandfront Player plugin

Made changes.

100mountains: perfect we just need to clean up the css now so the 5 buttons at the top of 'Analytics Dashboard' fit into one line at the top as they are spilling into two now

GitHub Copilot: I'll update the CSS to ensure the 5 stat boxes fit properly on one line. The issue is that the grid is trying to fit 5 columns but the responsive breakpoints need adjustment.

### [admin.css](vscode-remote://ssh-remote/var/www/html/wp-content/plugins/bandfront-analytics/assets/css/admin.css)

Update the admin CSS to better handle the 5-column grid layout:

````css
/* Analytics Dashboard Styles */
.bfa-analytics-wrap {
    margin: 20px 20px 20px 0;
}

.bfa-stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

/* 5-column grid for main analytics page */
.bfa-stats-grid-5 {
    grid-template-columns: repeat(5, 1fr);
    gap: 15px; /* Slightly smaller gap for 5 columns */
}

/* Responsive breakpoints for 5-column grid */
@media (max-width: 1600px) {
    .bfa-stats-grid-5 .bfa-stat-card {
        padding: 15px; /* Reduce padding on smaller screens */
    }
    
    .bfa-stats-grid-5 .bfa-stat-icon {
        font-size: 28px; /* Slightly smaller icon */
    }
    
    .bfa-stats-grid-5 .bfa-stat-value {
        font-size: 24px; /* Slightly smaller value text */
    }
}

@media (max-width: 1400px) {
    .bfa-stats-grid-5 {
        grid-template-columns: repeat(3, 1fr);
    }
}

@media (max-width: 768px) {
    .bfa-stats-grid-5 {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media (max-width: 480px) {
    .bfa-stats-grid-5 {
        grid-template-columns: 1fr;
    }
}

.bfa-stat-card {
    background: #fff;
    border: 1px solid #e0e0e0;
    border-radius: 8px;
    padding: 20px;
    display: flex;
    align-items: center;
    gap: 15px;
    transition: box-shadow 0.3s ease;
    min-width: 0; /* Prevent overflow */
}

.bfa-stat-card:hover {
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
}

.bfa-stat-icon {
    font-size: 32px;
    opacity: 0.8;
    flex-shrink: 0; /* Prevent icon from shrinking */
}

.bfa-stat-content {
    flex: 1;
    min-width: 0; /* Allow content to shrink */
}

.bfa-stat-value {
    font-size: 28px;
    font-weight: 600;
    color: #23282d;
    line-height: 1;
    white-space: nowrap; /* Prevent number wrapping */
    overflow: hidden;
    text-overflow: ellipsis;
}

.bfa-stat-label {
    color: #666;
    font-size: 14px;
    margin-top: 5px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.bfa-change-positive {
    color: #46b450;
}

.bfa-change-negative {
    color: #dc3232;
}

/* ...existing code... */
````

The key changes made:
1. Set `grid-template-columns: repeat(5, 1fr)` for the 5-column grid to ensure equal width columns
2. Reduced the gap from 20px to 15px for tighter spacing
3. Added a media query at 1600px to reduce padding and font sizes when space gets tight
4. Added `min-width: 0` to prevent grid items from overflowing
5. Added `white-space: nowrap` with `text-overflow: ellipsis` to handle long numbers gracefully
6. Made icons `flex-shrink: 0` to maintain their size

This should keep all 5 boxes on one line on most desktop screens while still being responsive on smaller viewports.

