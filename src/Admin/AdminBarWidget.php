<?php
namespace bfa\Admin;

use bfa\Plugin;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Handles the admin bar widget functionality
 */
class AdminBarWidget {
    
    private Plugin $plugin;
    
    public function __construct(Plugin $plugin) {
        $this->plugin = $plugin;
        $this->initHooks();
    }
    
    /**
     * Initialize hooks for admin bar widget
     */
    private function initHooks(): void {
        // Only add hooks if the widget is enabled in settings
        if (!$this->plugin->getConfig()->get('show_admin_bar_widget', true)) {
            return;
        }
        
        add_action('admin_bar_menu', [$this, 'addAdminBarWidget'], 100);
        add_action('wp_enqueue_scripts', [$this, 'enqueueAssets']);
        add_action('admin_enqueue_scripts', [$this, 'enqueueAssets']);
        add_action('wp_footer', [$this, 'renderExpandedWidget']);
        add_action('admin_footer', [$this, 'renderExpandedWidget']);
    }
    
    /**
     * Add widget to admin bar
     */
    public function addAdminBarWidget(\WP_Admin_Bar $adminBar): void {
        if (!current_user_can('manage_options')) {
            return;
        }
        
        $stats = $this->plugin->getDatabase()->getQuickStats();
        
        // Main expanded analytics bar - NO href to prevent navigation
        $adminBar->add_node([
            'id' => 'bfa-analytics-bar',
            'title' => $this->renderMainBarContent($stats),
            'href' => '#', // Use # instead of admin URL to prevent navigation
            'meta' => [
                'class' => 'bfa-analytics-main-bar',
                'html' => true
            ]
        ]);
    }
    
    /**
     * Render the main bar content with stats boxes
     */
    private function renderMainBarContent(array $stats): string {
        $change = 0;
        if (isset($stats['yesterday_views']) && $stats['yesterday_views'] > 0) {
            $change = round((($stats['today_views'] - $stats['yesterday_views']) / $stats['yesterday_views']) * 100, 1);
        }
        
        $changeClass = $change >= 0 ? 'positive' : 'negative';
        $changeIcon = $change >= 0 ? '📈' : '📉';
        
        $html = '<div class="bfa-admin-bar-analytics">';
        
        // Analytics icon and label
        $html .= '<div class="bfa-bar-brand">';
        $html .= '<span class="bfa-brand-icon">📊</span>';
        $html .= '<span class="bfa-brand-text">Analytics</span>';
        $html .= '</div>';
        
        // Stat boxes
        $html .= '<div class="bfa-bar-stats">';
        
        // Views today
        $html .= '<div class="bfa-stat-box bfa-views-box">';
        $html .= '<div class="bfa-stat-icon">👁️</div>';
        $html .= '<div class="bfa-stat-data">';
        $html .= '<div class="bfa-stat-number">' . $this->formatNumber($stats['today_views']) . '</div>';
        $html .= '<div class="bfa-stat-label">Views</div>';
        $html .= '</div>';
        $html .= '</div>';
        
        // Visitors today
        $html .= '<div class="bfa-stat-box bfa-visitors-box">';
        $html .= '<div class="bfa-stat-icon">👥</div>';
        $html .= '<div class="bfa-stat-data">';
        $html .= '<div class="bfa-stat-number">' . $this->formatNumber($stats['today_visitors']) . '</div>';
        $html .= '<div class="bfa-stat-label">Visitors</div>';
        $html .= '</div>';
        $html .= '</div>';
        
        // Music plays
        $html .= '<div class="bfa-stat-box bfa-plays-box">';
        $html .= '<div class="bfa-stat-icon">🎵</div>';
        $html .= '<div class="bfa-stat-data">';
        $html .= '<div class="bfa-stat-number">' . $this->formatNumber($stats['today_plays'] ?? 0) . '</div>';
        $html .= '<div class="bfa-stat-label">Plays</div>';
        $html .= '</div>';
        $html .= '</div>';
        
        // Change indicator
        $html .= '<div class="bfa-stat-box bfa-change-box bfa-change-' . $changeClass . '">';
        $html .= '<div class="bfa-stat-icon">' . $changeIcon . '</div>';
        $html .= '<div class="bfa-stat-data">';
        $html .= '<div class="bfa-stat-number">' . ($change >= 0 ? '+' : '') . $change . '%</div>';
        $html .= '<div class="bfa-stat-label">vs Yesterday</div>';
        $html .= '</div>';
        $html .= '</div>';
        
        // Mini chart container
        $html .= '<div class="bfa-mini-chart-container">';
        $html .= '<canvas id="bfa-admin-bar-chart" width="120" height="30"></canvas>';
        $html .= '</div>';
        
        $html .= '</div>'; // End stats
        $html .= '</div>'; // End analytics
        
        return $html;
    }
    
    /**
     * Format numbers for display
     */
    private function formatNumber(int $number): string {
        if ($number >= 1000000) {
            return round($number / 1000000, 1) . 'M';
        } elseif ($number >= 1000) {
            return round($number / 1000, 1) . 'K';
        }
        return (string) $number;
    }
    
    /**
     * Render expanded widget in footer for hover effect
     */
    public function renderExpandedWidget(): void {
        if (!is_admin_bar_showing() || !current_user_can('manage_options')) {
            return;
        }
        
        $stats = $this->plugin->getDatabase()->getQuickStats();
        ?>
        <div id="bfa-expanded-widget" class="bfa-expanded-widget" style="display: none;">
            <div class="bfa-expanded-header">
                <h3>📊 Analytics Overview</h3>
                <div class="bfa-live-indicator">
                    <span class="bfa-pulse-dot"></span>
                    <span><?php echo number_format($stats['active_users']); ?> active now</span>
                </div>
            </div>
            
            <div class="bfa-expanded-stats">
                <div class="bfa-expanded-stat">
                    <div class="bfa-expanded-stat-icon" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">👁️</div>
                    <div class="bfa-expanded-stat-content">
                        <div class="bfa-expanded-stat-number"><?php echo number_format($stats['today_views']); ?></div>
                        <div class="bfa-expanded-stat-label">Page Views Today</div>
                        <div class="bfa-expanded-stat-sublabel"><?php echo number_format($stats['today_visitors']); ?> unique visitors</div>
                    </div>
                </div>
                
                <div class="bfa-expanded-stat">
                    <div class="bfa-expanded-stat-icon" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">🎵</div>
                    <div class="bfa-expanded-stat-content">
                        <div class="bfa-expanded-stat-number"><?php echo number_format($stats['today_plays'] ?? 0); ?></div>
                        <div class="bfa-expanded-stat-label">Music Plays Today</div>
                        <div class="bfa-expanded-stat-sublabel">Tracks listened to</div>
                    </div>
                </div>
                
                <div class="bfa-expanded-stat">
                    <div class="bfa-expanded-stat-icon" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);">📈</div>
                    <div class="bfa-expanded-stat-content">
                        <?php
                        $weeklyViews = $stats['today_views'] * 7; // Simplified calculation
                        ?>
                        <div class="bfa-expanded-stat-number"><?php echo $this->formatNumber($weeklyViews); ?></div>
                        <div class="bfa-expanded-stat-label">Weekly Projection</div>
                        <div class="bfa-expanded-stat-sublabel">Based on today's trend</div>
                    </div>
                </div>
            </div>
            
            <div class="bfa-expanded-chart">
                <h4>Last 7 Days</h4>
                <canvas id="bfa-expanded-chart" width="300" height="100"></canvas>
            </div>
            
            <div class="bfa-expanded-footer">
                <a href="<?php echo admin_url('admin.php?page=bandfront-analytics'); ?>" class="bfa-expanded-button">
                    View Full Dashboard →
                </a>
            </div>
        </div>
        <?php
    }
    
    /**
     * Enqueue admin bar widget assets
     */
    public function enqueueAssets(): void {
        if (!is_admin_bar_showing() || !current_user_can('manage_options')) {
            return;
        }
        
        // Enqueue Chart.js for mini charts
        wp_enqueue_script(
            'chart-js-admin-bar',
            'https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js',
            [],
            '4.4.0'
        );
        
        wp_enqueue_style(
            'bfa-admin-bar',
            BFA_PLUGIN_URL . 'assets/css/admin-bar.css',
            [],
            BFA_VERSION
        );
        
        wp_enqueue_script(
            'bfa-admin-bar-widget',
            BFA_PLUGIN_URL . 'assets/js/admin-bar-widget.js',
            ['jquery', 'chart-js-admin-bar'],
            BFA_VERSION,
            true
        );
        
        // Localize with chart data
        wp_localize_script('bfa-admin-bar-widget', 'bfaAdminBarData', [
            'apiUrl' => rest_url('bandfront-analytics/v1/'),
            'nonce' => wp_create_nonce('wp_rest'),
            'chartData' => $this->getChartData(),
        ]);
    }
    
    /**
     * Get chart data for the last 7 days
     */
    private function getChartData(): array {
        $database = $this->plugin->getDatabase();
        $endDate = date('Y-m-d');
        $startDate = date('Y-m-d', strtotime('-6 days'));
        
        $stats = $database->getStats($startDate, $endDate, 'pageviews');
        
        $labels = [];
        $data = [];
        
        // Fill in missing days with 0
        for ($i = 6; $i >= 0; $i--) {
            $date = date('Y-m-d', strtotime("-{$i} days"));
            $labels[] = date('M j', strtotime($date));
            
            $found = false;
            foreach ($stats as $stat) {
                if ($stat['stat_date'] === $date) {
                    $data[] = (int) $stat['value'];
                    $found = true;
                    break;
                }
            }
            if (!$found) {
                $data[] = 0;
            }
        }
        
        return [
            'labels' => $labels,
            'datasets' => [[
                'data' => $data,
                'borderColor' => '#667eea',
                'backgroundColor' => 'rgba(102, 126, 234, 0.1)',
                'borderWidth' => 2,
                'tension' => 0.4,
                'pointRadius' => 0,
                'pointHoverRadius' => 4,
            ]]
        ];
    }
}