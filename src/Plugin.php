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