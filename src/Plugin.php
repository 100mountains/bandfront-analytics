<?php
namespace bfa;

use bfa\Admin\Admin;
// use bfa\Tracking\Tracker;
// use bfa\API\RestAPI;

class Plugin {
    private static ?Plugin $instance = null;
    
    private Config $config;
    private Database $database;
    private ?Admin $admin = null;
    // private ?Tracker $tracker = null;
    // private ?RestAPI $api = null;
    
    /**
     * Plugin file path
     */
    private string $file;
    
    /**
     * Constructor
     */
    public function __construct(string $file) {
        $this->file = $file;
        $this->initComponents();
        $this->initHooks();
    }
    
    /**
     * Initialize components
     */
    private function initComponents(): void {
        // Core components
        $this->config = new Config();
        $this->database = new Database();
        
        // Initialize tracking - commented out until Tracker class is created
        // if (class_exists('bfa\Tracking\Tracker')) {
        //     $this->tracker = new Tracker($this);
        // }
        
        // Initialize admin interface
        if (is_admin()) {
            $this->admin = new Admin($this);
        }
        
        // Initialize REST API - commented out until RestAPI class is created
        // if (class_exists('bfa\API\RestAPI')) {
        //     $this->api = new RestAPI($this);
        // }
    }
    
    /**
     * Initialize hooks
     */
    private function initHooks(): void {
        register_activation_hook($this->file, [$this, 'activate']);
        register_deactivation_hook($this->file, [$this, 'deactivate']);
        
        // Add other global hooks here
        add_action('init', [$this, 'init']);
    }
    
    /**
     * Plugin initialization
     */
    public function init(): void {
        // Load textdomain
        load_plugin_textdomain('bandfront-analytics', false, dirname(plugin_basename($this->file)) . '/languages');
    }
    
    /**
     * Get singleton instance
     */
    public static function getInstance(string $file = ''): Plugin {
        if (self::$instance === null) {
            self::$instance = new self($file);
        }
        return self::$instance;
    }
    
    /**
     * Activation hook
     */
    public function activate(): void {
        $this->database->createTables();
        $this->config->setDefaults();
        
        // Schedule cron jobs
        if (!wp_next_scheduled('bfa_cleanup_old_data')) {
            wp_schedule_event(time(), 'daily', 'bfa_cleanup_old_data');
        }
        
        flush_rewrite_rules();
    }
    
    /**
     * Deactivation hook
     */
    public function deactivate(): void {
        // Clear scheduled events
        wp_clear_scheduled_hook('bfa_cleanup_old_data');
        
        flush_rewrite_rules();
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
     * Get admin instance
     */
    public function getAdmin(): ?Admin {
        return $this->admin;
    }
}