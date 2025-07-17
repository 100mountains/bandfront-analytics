<?php
namespace bfa;

use bfa\UI\Columns;
use bfa\Admin\Admin;
use bfa\Admin\AdminBarWidget;
use bfa\Admin\DashboardWidget;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Main plugin class
 */
class Plugin {
    
    private Config $config;
    private Database $database;
    private Tracker $tracker;
    private ?Admin $admin = null;
    private ?Api $api = null;
    private ?Columns $columns = null;
    private ?AdminBarWidget $adminBarWidget = null;
    private ?DashboardWidget $dashboardWidget = null;
    
    public function __construct(string $file) {
        $this->config = new Config();
        $this->database = new Database();
        $this->tracker = new Tracker($this);
        
        $this->init();
    }
    
    /**
     * Initialize the plugin
     */
    private function init(): void {
        // Initialize UI components
        $this->columns = new Columns($this);
        
        // Initialize admin components
        if (is_admin()) {
            $this->admin = new Admin($this);
            $this->dashboardWidget = new DashboardWidget($this);
        }
        
        // Initialize admin bar widget
        $this->adminBarWidget = new AdminBarWidget($this);
        
        // API initialization
        $this->api = new Api($this);
        
        // Register hooks
        $this->registerHooks();
    }
    
    /**
     * Register plugin hooks
     */
    private function registerHooks(): void {
        // Cleanup hook
        add_action('bfa_cleanup', [$this, 'performCleanup']);
        
        // Schedule cleanup if not already scheduled
        if (!wp_next_scheduled('bfa_cleanup')) {
            wp_schedule_event(time(), 'daily', 'bfa_cleanup');
        }
    }
    
    /**
     * Perform cleanup tasks
     */
    public function performCleanup(): void {
        $retentionDays = $this->config->get('retention_days', 365);
        $this->database->cleanOldData($retentionDays);
    }
    
    /**
     * Get config instance
     */
    public function getConfig(): Config {
        return $this->config;
    }
    
    /**
     * Get database instance
     */
    public function getDatabase(): Database {
        return $this->database;
    }
    
    /**
     * Get tracker instance
     */
    public function getTracker(): Tracker {
        return $this->tracker;
    }
}