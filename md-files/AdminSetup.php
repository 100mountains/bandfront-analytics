<?php
namespace BandfrontAnalytics\Admin;

use BandfrontAnalytics\Admin\DatabaseMonitor;

class AdminSetup {
    
    private $dashboard;
    private $reports;
    private $settings;
    private $database_monitor;
    
    public function __construct() {
        $this->dashboard = new Dashboard();
        $this->reports = new Reports();
        $this->settings = new Settings();
        $this->database_monitor = new DatabaseMonitor();
        
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
    }
    
    /**
     * Add admin menu items
     */
    public function add_admin_menu() {
        add_menu_page(
            'Bandfront Analytics',
            'BF Analytics',
            'manage_options',
            'bandfront-analytics',
            [$this->dashboard, 'render'],
            'dashicons-chart-line',
            6
        );
        
        add_submenu_page(
            'bandfront-analytics',
            'Reports',
            'Reports',
            'manage_options',
            'bandfront-analytics-reports',
            [$this->reports, 'render']
        );
        
        add_submenu_page(
            'bandfront-analytics',
            'Settings',
            'Settings',
            'manage_options',
            'bandfront-analytics-settings',
            [$this->settings, 'render']
        );
    }
    
    /**
     * Enqueue admin assets
     */
    public function enqueue_admin_assets() {
        // Enqueue your admin styles and scripts here
    }
}