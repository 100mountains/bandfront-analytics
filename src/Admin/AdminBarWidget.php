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
    }
    
    /**
     * Add widget to admin bar
     */
    public function addAdminBarWidget(\WP_Admin_Bar $adminBar): void {
        if (!current_user_can('manage_options')) {
            return;
        }
        
        $stats = $this->plugin->getDatabase()->getQuickStats();
        
        // Main node
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
            'title' => $this->renderStatsDropdown($stats),
            'meta' => [
                'html' => true
            ]
        ]);
        
        // Quick links
        $adminBar->add_node([
            'id' => 'bfa-view-dashboard',
            'parent' => 'bfa-stats',
            'title' => __('View Dashboard', 'bandfront-analytics'),
            'href' => admin_url('admin.php?page=bandfront-analytics')
        ]);
        
        $adminBar->add_node([
            'id' => 'bfa-view-settings',
            'parent' => 'bfa-stats',
            'title' => __('Settings', 'bandfront-analytics'),
            'href' => admin_url('admin.php?page=bandfront-analytics-settings')
        ]);
    }
    
    /**
     * Render the stats dropdown HTML
     */
    private function renderStatsDropdown(array $stats): string {
        $html = '<div class="bfa-admin-bar-details">';
        
        // Today's stats
        $html .= '<div class="bfa-stat-section">';
        $html .= '<h4>' . __('Today', 'bandfront-analytics') . '</h4>';
        
        $html .= '<div class="bfa-stat-row">';
        $html .= '<span>' . __('Page Views:', 'bandfront-analytics') . '</span>';
        $html .= '<strong>' . number_format($stats['today_views']) . '</strong>';
        $html .= '</div>';
        
        $html .= '<div class="bfa-stat-row">';
        $html .= '<span>' . __('Visitors:', 'bandfront-analytics') . '</span>';
        $html .= '<strong>' . number_format($stats['today_visitors']) . '</strong>';
        $html .= '</div>';
        
        $html .= '<div class="bfa-stat-row">';
        $html .= '<span>' . __('Music Plays:', 'bandfront-analytics') . '</span>';
        $html .= '<strong>' . number_format($stats['today_plays'] ?? 0) . '</strong>';
        $html .= '</div>';
        
        $html .= '</div>'; // End today section
        
        // Live stats
        $html .= '<div class="bfa-stat-section">';
        $html .= '<h4>' . __('Live', 'bandfront-analytics') . '</h4>';
        
        $html .= '<div class="bfa-stat-row">';
        $html .= '<span>' . __('Active Now:', 'bandfront-analytics') . '</span>';
        $html .= '<strong class="bfa-live-count">' . number_format($stats['active_users']) . '</strong>';
        $html .= '</div>';
        
        $html .= '</div>'; // End live section
        
        // Comparison
        if (isset($stats['yesterday_views']) && $stats['yesterday_views'] > 0) {
            $change = round((($stats['today_views'] - $stats['yesterday_views']) / $stats['yesterday_views']) * 100, 1);
            $changeClass = $change >= 0 ? 'bfa-positive' : 'bfa-negative';
            $changeText = ($change >= 0 ? '+' : '') . $change . '%';
            
            $html .= '<div class="bfa-stat-section bfa-stat-comparison">';
            $html .= '<div class="bfa-stat-row">';
            $html .= '<span>' . __('vs Yesterday:', 'bandfront-analytics') . '</span>';
            $html .= '<strong class="' . $changeClass . '">' . $changeText . '</strong>';
            $html .= '</div>';
            $html .= '</div>';
        }
        
        $html .= '</div>';
        
        return $html;
    }
    
    /**
     * Enqueue admin bar widget assets
     */
    public function enqueueAssets(): void {
        if (!is_admin_bar_showing() || !current_user_can('manage_options')) {
            return;
        }
        
        wp_enqueue_style(
            'bfa-admin-bar',
            BFA_PLUGIN_URL . 'assets/css/admin-bar.css',
            [],
            BFA_VERSION
        );
        
        // Add inline JavaScript for live updates
        wp_add_inline_script('jquery', $this->getInlineScript(), 'after');
    }
    
    /**
     * Get inline JavaScript for live updates
     */
    private function getInlineScript(): string {
        return "
        jQuery(document).ready(function($) {
            // Update admin bar stats every 30 seconds
            function updateAdminBarStats() {
                $.get({
                    url: '" . rest_url('bandfront-analytics/v1/quick-stats') . "',
                    headers: {
                        'X-WP-Nonce': '" . wp_create_nonce('wp_rest') . "'
                    },
                    success: function(data) {
                        if (data.today_views !== undefined) {
                            $('#wp-admin-bar-bfa-stats .ab-label').text(data.today_views.toLocaleString());
                        }
                        if (data.active_users !== undefined) {
                            $('.bfa-live-count').text(data.active_users.toLocaleString());
                        }
                    }
                });
            }
            
            // Run update every 30 seconds
            setInterval(updateAdminBarStats, 30000);
        });
        ";
    }
}
