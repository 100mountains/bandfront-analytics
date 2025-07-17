<?php
namespace bfa;

use bfa\UI\Columns;
use bfa\Admin\Admin;

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
        
        // Admin initialization
        if (is_admin()) {
            $this->admin = new Admin($this);
        }
        
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
        
        // Admin bar widget - check setting
        if ($this->config->get('show_admin_bar_widget', true)) {
            add_action('admin_bar_menu', [$this, 'addAdminBarWidget'], 100);
            add_action('wp_enqueue_scripts', [$this, 'enqueueAdminBarAssets']);
            add_action('admin_enqueue_scripts', [$this, 'enqueueAdminBarAssets']);
        }
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
        
        // Add submenu with mini stats
        $adminBar->add_node([
            'id' => 'bfa-stats-details',
            'parent' => 'bfa-stats',
            'title' => '<div class="bfa-admin-bar-details">' .
                '<div class="bfa-stat-row">' .
                    '<span>' . __('Visitors:', 'bandfront-analytics') . '</span>' .
                    '<strong>' . number_format($stats['today_visitors']) . '</strong>' .
                '</div>' .
                '<div class="bfa-stat-row">' .
                    '<span>' . __('Active Now:', 'bandfront-analytics') . '</span>' .
                    '<strong>' . number_format($stats['active_users']) . '</strong>' .
                '</div>' .
                '<div class="bfa-stat-row">' .
                    '<span>' . __('Plays Today:', 'bandfront-analytics') . '</span>' .
                    '<strong>' . number_format($stats['today_plays'] ?? 0) . '</strong>' .
                '</div>' .
            '</div>',
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