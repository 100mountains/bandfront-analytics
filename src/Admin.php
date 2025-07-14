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