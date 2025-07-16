<?php
namespace bfa;

use bfa\UI\Columns;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Main Plugin Class for Bandfront Analytics
 */
class Plugin {
    
    private string $file;
    private string $version = '1.0.0';
    
    private ?Config $config = null;
    private ?Database $database = null;
    private ?Analytics $analytics = null;
    private ?Tracker $tracker = null;
    private ?Api $api = null;
    private ?Admin $admin = null;
    private ?Columns $columns = null;
    
    public function __construct(string $file) {
        $this->file = $file;
        
        // Initialize components
        $this->config = new Config();
        $this->database = new Database();
        $this->analytics = new Analytics($this);
        $this->tracker = new Tracker($this);
        $this->api = new Api($this);
        
        // Initialize UI components
        $this->columns = new Columns($this);
        
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
        
        // Admin bar widget - check setting
        if ($this->config->get('show_admin_bar_widget', true)) {
            add_action('admin_bar_menu', [$this, 'addAdminBarWidget'], 100);
            add_action('wp_enqueue_scripts', [$this, 'enqueueAdminBarAssets']);
            add_action('admin_enqueue_scripts', [$this, 'enqueueAdminBarAssets']);
        }
        
        // REST API - check setting
        if ($this->config->get('enable_api', true)) {
            add_action('rest_api_init', [$this->api, 'registerRoutes']);
        }
        
        // Column functionality is now handled by the Columns class
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
        // Check if tracking is enabled
        if (!$this->config->get('tracking_enabled', true)) {
            return;
        }
        
        // Check admin exclusion
        if (is_user_logged_in() && current_user_can('manage_options') && $this->config->get('exclude_admins', true)) {
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
            'trackingEnabled' => true,
            'apiEnabled' => $this->config->get('enable_api', true),
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
    
    public function getColumns(): Columns {
        return $this->columns;
    }
    
    public function getAnalytics(): Analytics {
        return $this->analytics;
    }
}