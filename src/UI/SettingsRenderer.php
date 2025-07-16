<?php
namespace bfa\UI;

use bfa\Plugin;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Renders settings page tabs and content
 */
class SettingsRenderer {
    
    private Plugin $plugin;
    private TabManager $tabManager;
    
    public function __construct(Plugin $plugin) {
        $this->plugin = $plugin;
        $this->tabManager = new TabManager($plugin);
        $this->registerTabs();
    }
    
    /**
     * Register settings tabs
     */
    private function registerTabs(): void {
        $this->tabManager->registerTab(
            'general',
            __('General', 'bandfront-analytics'),
            [$this, 'renderGeneralTab'],
            10
        );
        
        $this->tabManager->registerTab(
            'admin-options',
            __('Admin Options', 'bandfront-analytics'),
            [$this, 'renderAdminOptionsTab'],
            20
        );
        
        $this->tabManager->registerTab(
            'rest-api',
            __('REST API', 'bandfront-analytics'),
            [$this, 'renderRestApiTab'],
            30
        );
        
        $this->tabManager->registerTab(
            'privacy',
            __('Privacy', 'bandfront-analytics'),
            [$this, 'renderPrivacyTab'],
            40
        );
    }
    
    /**
     * Render the complete settings page
     */
    public function render(): void {
        // Handle form submission
        if (isset($_POST['bfa_save_settings']) && wp_verify_nonce($_POST['bfa_nonce'], 'bfa_settings')) {
            $this->saveSettings();
        }
        
        ?>
        <div class="wrap bfa-settings-wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            
            <?php settings_errors('bfa_messages'); ?>
            
            <?php $this->tabManager->renderNavigation(admin_url('admin.php?page=bandfront-analytics-settings')); ?>
            
            <form method="post" action="" class="bfa-settings-form">
                <?php wp_nonce_field('bfa_settings', 'bfa_nonce'); ?>
                
                <div class="bfa-tab-content">
                    <?php $this->tabManager->renderContent(); ?>
                </div>
                
                <p class="submit">
                    <input type="submit" name="bfa_save_settings" class="button-primary" 
                           value="<?php esc_attr_e('Save Settings', 'bandfront-analytics'); ?>">
                </p>
            </form>
        </div>
        <?php
    }
    
    /**
     * Render General settings tab
     */
    public function renderGeneralTab(): void {
        $config = $this->plugin->getConfig();
        ?>
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
                <th scope="row"><?php esc_html_e('Performance', 'bandfront-analytics'); ?></th>
                <td>
                    <label>
                        <?php esc_html_e('Sampling threshold', 'bandfront-analytics'); ?><br>
                        <input type="number" name="sampling_threshold" class="regular-text"
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
                        <input type="number" name="retention_days" class="small-text"
                               value="<?php echo esc_attr($config->get('retention_days')); ?>">
                        <?php esc_html_e('days', 'bandfront-analytics'); ?>
                    </label>
                </td>
            </tr>
        </table>
        <?php
    }
    
    /**
     * Render Admin Options tab
     */
    public function renderAdminOptionsTab(): void {
        $config = $this->plugin->getConfig();
        ?>
        <h2><?php esc_html_e('Admin Display Options', 'bandfront-analytics'); ?></h2>
        
        <table class="form-table">
            <tr>
                <th scope="row"><?php esc_html_e('Admin Columns', 'bandfront-analytics'); ?></th>
                <td>
                    <fieldset>
                        <label>
                            <input type="checkbox" name="show_post_columns" value="1" 
                                   <?php checked($config->get('show_post_columns', true)); ?>>
                            <?php esc_html_e('Show view count column for posts/pages', 'bandfront-analytics'); ?>
                        </label><br>
                        
                        <label>
                            <input type="checkbox" name="show_product_columns" value="1" 
                                   <?php checked($config->get('show_product_columns', true)); ?>>
                            <?php esc_html_e('Show analytics columns for WooCommerce products', 'bandfront-analytics'); ?>
                        </label><br>
                        
                        <label>
                            <input type="checkbox" name="show_user_columns" value="1" 
                                   <?php checked($config->get('show_user_columns', true)); ?>>
                            <?php esc_html_e('Show login analytics columns for users', 'bandfront-analytics'); ?>
                        </label>
                    </fieldset>
                </td>
            </tr>
            
            <tr>
                <th scope="row"><?php esc_html_e('Admin Bar', 'bandfront-analytics'); ?></th>
                <td>
                    <label>
                        <input type="checkbox" name="show_admin_bar_widget" value="1" 
                               <?php checked($config->get('show_admin_bar_widget', true)); ?>>
                        <?php esc_html_e('Show analytics widget in admin bar', 'bandfront-analytics'); ?>
                    </label>
                </td>
            </tr>
            
            <tr>
                <th scope="row"><?php esc_html_e('Dashboard Widget', 'bandfront-analytics'); ?></th>
                <td>
                    <label>
                        <input type="checkbox" name="show_dashboard_widget" value="1" 
                               <?php checked($config->get('show_dashboard_widget', true)); ?>>
                        <?php esc_html_e('Show analytics widget on dashboard', 'bandfront-analytics'); ?>
                    </label>
                </td>
            </tr>
        </table>
        <?php
    }
    
    /**
     * Render REST API tab
     */
    public function renderRestApiTab(): void {
        $config = $this->plugin->getConfig();
        $apiEnabled = $config->get('enable_api', true);
        ?>
        <h2><?php esc_html_e('REST API Settings', 'bandfront-analytics'); ?></h2>
        
        <table class="form-table">
            <tr>
                <th scope="row"><?php esc_html_e('API Access', 'bandfront-analytics'); ?></th>
                <td>
                    <label>
                        <input type="checkbox" name="enable_api" value="1" id="bfa-enable-api"
                               <?php checked($apiEnabled); ?>>
                        <?php esc_html_e('Enable REST API endpoints', 'bandfront-analytics'); ?>
                    </label>
                </td>
            </tr>
        </table>
        
        <?php if ($apiEnabled): ?>
            <!-- REST Traffic Monitor -->
            <div class="bfa-api-monitor">
                <h3><?php esc_html_e('API Traffic Monitor', 'bandfront-analytics'); ?></h3>
                <div class="bfa-traffic-box" id="bfa-api-traffic">
                    <div class="bfa-traffic-header">
                        <span class="bfa-traffic-status">● <?php esc_html_e('Live', 'bandfront-analytics'); ?></span>
                        <button type="button" class="button button-small" id="bfa-clear-traffic">
                            <?php esc_html_e('Clear', 'bandfront-analytics'); ?>
                        </button>
                    </div>
                    <div class="bfa-traffic-log" id="bfa-traffic-log">
                        <!-- Traffic will be loaded here via JavaScript -->
                    </div>
                </div>
            </div>
            
            <!-- Available Endpoints -->
            <div class="bfa-api-endpoints">
                <h3><?php esc_html_e('Available Endpoints', 'bandfront-analytics'); ?></h3>
                <?php $this->renderApiEndpoints(); ?>
            </div>
        <?php else: ?>
            <div class="notice notice-info inline">
                <p><?php esc_html_e('Enable the REST API to view endpoints and monitor traffic.', 'bandfront-analytics'); ?></p>
            </div>
        <?php endif; ?>
        <?php
    }
    
    /**
     * Render Privacy tab
     */
    public function renderPrivacyTab(): void {
        $config = $this->plugin->getConfig();
        ?>
        <h2><?php esc_html_e('Privacy Settings', 'bandfront-analytics'); ?></h2>
        
        <table class="form-table">
            <tr>
                <th scope="row"><?php esc_html_e('User Privacy', 'bandfront-analytics'); ?></th>
                <td>
                    <fieldset>
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
                    </fieldset>
                </td>
            </tr>
        </table>
        <?php
    }
    
    /**
     * Render API endpoints list
     */
    private function renderApiEndpoints(): void {
        // Get our registered endpoints directly from the API class
        $namespace = 'bandfront-analytics/v1';
        
        // Define our endpoints manually since we know what they are
        $endpoints = [
            [
                'route' => '/bandfront-analytics/v1/track',
                'methods' => ['POST'],
                'description' => __('Track analytics events', 'bandfront-analytics'),
            ],
            [
                'route' => '/bandfront-analytics/v1/stats',
                'methods' => ['GET'],
                'description' => __('Get statistics data', 'bandfront-analytics'),
            ],
            [
                'route' => '/bandfront-analytics/v1/quick-stats',
                'methods' => ['GET'],
                'description' => __('Get quick stats summary', 'bandfront-analytics'),
            ],
            [
                'route' => '/bandfront-analytics/v1/chart',
                'methods' => ['GET'],
                'description' => __('Get chart data', 'bandfront-analytics'),
            ],
            [
                'route' => '/bandfront-analytics/v1/top-posts',
                'methods' => ['GET'],
                'description' => __('Get top posts by views', 'bandfront-analytics'),
            ],
            [
                'route' => '/bandfront-analytics/v1/top-tracks',
                'methods' => ['GET'],
                'description' => __('Get top music tracks', 'bandfront-analytics'),
            ],
            [
                'route' => '/bandfront-analytics/v1/music-stats',
                'methods' => ['GET'],
                'description' => __('Get music statistics', 'bandfront-analytics'),
            ],
            [
                'route' => '/bandfront-analytics/v1/active-users',
                'methods' => ['GET'],
                'description' => __('Get currently active users', 'bandfront-analytics'),
            ],
            [
                'route' => '/bandfront-analytics/v1/member-stats',
                'methods' => ['GET'],
                'description' => __('Get member statistics', 'bandfront-analytics'),
            ],
            [
                'route' => '/bandfront-analytics/v1/member-growth',
                'methods' => ['GET'],
                'description' => __('Get member growth data', 'bandfront-analytics'),
            ],
            [
                'route' => '/bandfront-analytics/v1/member-activity',
                'methods' => ['GET'],
                'description' => __('Get member activity data', 'bandfront-analytics'),
            ],
            [
                'route' => '/bandfront-analytics/v1/user-logins',
                'methods' => ['GET'],
                'description' => __('Get user login statistics', 'bandfront-analytics'),
            ],
        ];
        
        ?>
        <div class="bfa-endpoints-list">
            <p class="description">
                <?php 
                printf(
                    esc_html__('Total endpoints available: %d', 'bandfront-analytics'), 
                    count($endpoints)
                ); 
                ?>
            </p>
            
            <?php foreach ($endpoints as $endpoint): ?>
                <div class="bfa-endpoint-item">
                    <div class="bfa-endpoint-route">
                        <code><?php echo esc_html($endpoint['route']); ?></code>
                        <?php if ($endpoint['description']): ?>
                            <span class="description"><?php echo esc_html($endpoint['description']); ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="bfa-endpoint-methods">
                        <?php foreach ($endpoint['methods'] as $method): ?>
                            <span class="bfa-method-badge bfa-method-<?php echo esc_attr(strtolower($method)); ?>">
                                <?php echo esc_html($method); ?>
                            </span>
                        <?php endforeach; ?>
                    </div>
                    <div class="bfa-endpoint-url">
                        <small><?php echo esc_html(rest_url($endpoint['route'])); ?></small>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <?php
    }
    
    /**
     * Get endpoint description based on route
     */
    private function getEndpointDescription(string $route): string {
        $descriptions = [
            '/bandfront-analytics/v1/track' => __('Track analytics events', 'bandfront-analytics'),
            '/bandfront-analytics/v1/stats' => __('Get statistics data', 'bandfront-analytics'),
            '/bandfront-analytics/v1/quick-stats' => __('Get quick stats summary', 'bandfront-analytics'),
            '/bandfront-analytics/v1/chart' => __('Get chart data', 'bandfront-analytics'),
            '/bandfront-analytics/v1/top-posts' => __('Get top posts by views', 'bandfront-analytics'),
            '/bandfront-analytics/v1/top-tracks' => __('Get top music tracks', 'bandfront-analytics'),
            '/bandfront-analytics/v1/music-stats' => __('Get music statistics', 'bandfront-analytics'),
            '/bandfront-analytics/v1/active-users' => __('Get currently active users', 'bandfront-analytics'),
            '/bandfront-analytics/v1/member-stats' => __('Get member statistics', 'bandfront-analytics'),
            '/bandfront-analytics/v1/member-growth' => __('Get member growth data', 'bandfront-analytics'),
            '/bandfront-analytics/v1/member-activity' => __('Get member activity data', 'bandfront-analytics'),
            '/bandfront-analytics/v1/user-logins' => __('Get user login statistics', 'bandfront-analytics'),
        ];
        
        return $descriptions[$route] ?? '';
    }
    
    /**
     * Save settings from all tabs
     */
    private function saveSettings(): void {
        $currentTab = $this->tabManager->getCurrentTab();
        $config = $this->plugin->getConfig();
        
        // Collect settings based on current tab
        $settings = [];
        
        switch ($currentTab) {
            case 'general':
                $settings = [
                    'tracking_enabled' => !empty($_POST['tracking_enabled']),
                    'sampling_threshold' => intval($_POST['sampling_threshold'] ?? 10000),
                    'retention_days' => intval($_POST['retention_days'] ?? 365),
                ];
                break;
                
            case 'admin-options':
                $settings = [
                    'show_post_columns' => !empty($_POST['show_post_columns']),
                    'show_product_columns' => !empty($_POST['show_product_columns']),
                    'show_user_columns' => !empty($_POST['show_user_columns']),
                    'show_admin_bar_widget' => !empty($_POST['show_admin_bar_widget']),
                    'show_dashboard_widget' => !empty($_POST['show_dashboard_widget']),
                ];
                break;
                
            case 'rest-api':
                $settings = [
                    'enable_api' => !empty($_POST['enable_api']),
                ];
                break;
                
            case 'privacy':
                $settings = [
                    'exclude_admins' => !empty($_POST['exclude_admins']),
                    'respect_dnt' => !empty($_POST['respect_dnt']),
                    'anonymize_ip' => !empty($_POST['anonymize_ip']),
                ];
                break;
        }
        
        $config->save($settings);
        
        add_settings_error(
            'bfa_messages',
            'bfa_message',
            __('Settings saved successfully!', 'bandfront-analytics'),
            'success'
        );
    }
    
    /**
     * Get TabManager instance
     */
    public function getTabManager(): TabManager {
        return $this->tabManager;
    }
}
