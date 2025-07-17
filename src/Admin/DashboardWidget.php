<?php
namespace bfa\Admin;

use bfa\Plugin;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Handles the WordPress dashboard widget
 */
class DashboardWidget {
    
    private Plugin $plugin;
    
    public function __construct(Plugin $plugin) {
        $this->plugin = $plugin;
        $this->initHooks();
    }
    
    /**
     * Initialize hooks for dashboard widget
     */
    private function initHooks(): void {
        // Only add hooks if the widget is enabled in settings
        if (!$this->plugin->getConfig()->get('show_dashboard_widget', true)) {
            return;
        }
        
        add_action('wp_dashboard_setup', [$this, 'registerWidget']);
    }
    
    /**
     * Register the dashboard widget
     */
    public function registerWidget(): void {
        wp_add_dashboard_widget(
            'bfa_dashboard_widget',
            __('Analytics Overview', 'bandfront-analytics'),
            [$this, 'render'],
            null, // No control callback
            null, // No callback args
            'normal', // Context
            'high' // Priority
        );
    }
    
    /**
     * Render the dashboard widget
     */
    public function render(): void {
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
                <div class="bfa-widget-stat">
                    <strong><?php echo number_format($quickStats['today_plays'] ?? 0); ?></strong>
                    <span><?php esc_html_e('Plays', 'bandfront-analytics'); ?></span>
                </div>
                <div class="bfa-widget-stat">
                    <strong><?php echo number_format($quickStats['active_users']); ?></strong>
                    <span><?php esc_html_e('Active Now', 'bandfront-analytics'); ?></span>
                </div>
            </div>
            
            <div class="bfa-widget-chart">
                <canvas id="bfa-dashboard-chart" height="150"></canvas>
            </div>
            
            <div class="bfa-widget-actions">
                <a href="<?php echo admin_url('admin.php?page=bandfront-analytics'); ?>" class="button button-primary">
                    <?php esc_html_e('View Full Report', 'bandfront-analytics'); ?>
                </a>
                <a href="<?php echo admin_url('admin.php?page=bandfront-play-analytics'); ?>" class="button">
                    <?php esc_html_e('Play Analytics', 'bandfront-analytics'); ?>
                </a>
            </div>
            
            <?php
            // Add percentage change
            if ($quickStats['yesterday_views'] > 0) {
                $change = round((($quickStats['today_views'] - $quickStats['yesterday_views']) / $quickStats['yesterday_views']) * 100, 1);
                $changeClass = $change >= 0 ? 'positive' : 'negative';
                ?>
                <div class="bfa-widget-footer">
                    <span class="bfa-change-<?php echo $changeClass; ?>">
                        <?php echo ($change >= 0 ? '+' : '') . $change; ?>% <?php esc_html_e('vs yesterday', 'bandfront-analytics'); ?>
                    </span>
                </div>
                <?php
            }
            ?>
        </div>
        
        <style>
            .bfa-dashboard-widget {
                margin: -12px -12px -12px -12px;
            }
            .bfa-widget-stats {
                display: grid;
                grid-template-columns: repeat(4, 1fr);
                gap: 1px;
                background: #ddd;
                margin-bottom: 20px;
            }
            .bfa-widget-stat {
                background: #fff;
                padding: 15px;
                text-align: center;
            }
            .bfa-widget-stat strong {
                display: block;
                font-size: 24px;
                line-height: 1.2;
                color: #23282d;
                font-weight: 600;
            }
            .bfa-widget-stat span {
                display: block;
                margin-top: 5px;
                color: #666;
                font-size: 12px;
            }
            .bfa-widget-chart {
                padding: 0 12px;
                margin-bottom: 20px;
            }
            .bfa-widget-actions {
                padding: 0 12px 12px;
                display: flex;
                gap: 10px;
            }
            .bfa-widget-actions .button {
                flex: 1;
                text-align: center;
            }
            .bfa-widget-footer {
                padding: 12px;
                background: #f5f5f5;
                border-top: 1px solid #ddd;
                text-align: center;
            }
            .bfa-change-positive {
                color: #46b450;
                font-weight: 600;
            }
            .bfa-change-negative {
                color: #dc3232;
                font-weight: 600;
            }
            
            @media screen and (max-width: 1280px) {
                .bfa-widget-stats {
                    grid-template-columns: repeat(2, 1fr);
                }
            }
        </style>
        <?php
    }
}
