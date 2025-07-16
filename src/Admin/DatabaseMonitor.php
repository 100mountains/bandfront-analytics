<?php
namespace bfa\Admin;

use bfa\Utils\DbTest;
use bfa\Utils\DbClean;

class DatabaseMonitor {
    
    public function __construct() {
        // Add AJAX handlers for test actions
        add_action('wp_ajax_bfa_generate_test_events', [$this, 'ajax_generate_test_events']);
        add_action('wp_ajax_bfa_clean_test_events', [$this, 'ajax_clean_test_events']);
    }
    
    public function render() {
        ?>
        <div class="wrap bfa-database-monitor">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            
            <div class="bfa-monitor-grid">
                <!-- Activity Monitor -->
                <div class="bfa-monitor-section">
                    <h2><?php _e('Real-time Activity', 'bandfront-analytics'); ?></h2>
                    <div id="bfa-activity-monitor" class="bfa-activity-monitor">
                        <div class="bfa-activity-header">
                            <span class="bfa-activity-status">
                                <span class="bfa-status-dot"></span>
                                <?php _e('Monitoring', 'bandfront-analytics'); ?>
                            </span>
                            <button type="button" class="button button-small" id="bfa-pause-monitor">
                                <?php _e('Pause', 'bandfront-analytics'); ?>
                            </button>
                        </div>
                        <div class="bfa-activity-list">
                            <!-- Activity items will be inserted here -->
                        </div>
                    </div>
                </div>
                
                <!-- Database Stats -->
                <div class="bfa-monitor-section">
                    <h2><?php _e('Database Statistics', 'bandfront-analytics'); ?></h2>
                    <div class="bfa-db-stats">
                        <?php $this->renderDatabaseStats(); ?>
                    </div>
                </div>
            </div>
            
            <!-- Performance Metrics -->
            <div class="bfa-monitor-section">
                <h2><?php _e('Performance Metrics', 'bandfront-analytics'); ?></h2>
                <div class="bfa-performance-grid">
                    <?php $this->renderPerformanceMetrics(); ?>
                </div>
            </div>
            
            <!-- Test Actions Section -->
            <div class="bfa-test-actions" style="margin-top: 30px; padding: 20px; background: #f1f1f1; border-radius: 5px;">
                <h2><?php _e('Test Actions', 'bandfront-analytics'); ?></h2>
                <p><?php _e('Use these tools to generate test data and verify the activity monitor is working correctly.', 'bandfront-analytics'); ?></p>
                
                <div class="button-group" style="margin-top: 15px;">
                    <button type="button" class="button button-primary" id="bfa-generate-test-events">
                        <span class="dashicons dashicons-randomize" style="vertical-align: middle;"></span>
                        <?php _e('Generate Test Events', 'bandfront-analytics'); ?>
                    </button>
                    
                    <button type="button" class="button button-secondary" id="bfa-clean-test-events" style="margin-left: 10px;">
                        <span class="dashicons dashicons-trash" style="vertical-align: middle;"></span>
                        <?php _e('Clean Test Data', 'bandfront-analytics'); ?>
                    </button>
                    
                    <span class="spinner" style="float: none; margin-left: 10px;"></span>
                </div>
                
                <div id="bfa-test-results" style="margin-top: 15px; display: none;">
                    <div class="notice notice-info inline">
                        <p></p>
                    </div>
                </div>
            </div>
        </div>
        
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            // Activity monitor functionality
            let activityMonitor = {
                paused: false,
                interval: null,
                
                init: function() {
                    this.startMonitoring();
                    this.bindEvents();
                },
                
                startMonitoring: function() {
                    this.fetchActivity();
                    this.interval = setInterval(() => {
                        if (!this.paused) {
                            this.fetchActivity();
                        }
                    }, 3000); // Update every 3 seconds
                },
                
                fetchActivity: function() {
                    $.post(ajaxurl, {
                        action: 'bfa_get_db_activity',
                        nonce: '<?php echo wp_create_nonce('bfa_ajax'); ?>'
                    }, (response) => {
                        if (response.success) {
                            this.updateActivityList(response.data.activity);
                        }
                    });
                },
                
                updateActivityList: function(activities) {
                    const $list = $('.bfa-activity-list');
                    $list.empty();
                    
                    activities.forEach(activity => {
                        const $item = $('<div class="bfa-activity-item">');
                        $item.html(`
                            <span class="bfa-activity-time">${activity.time}</span>
                            <span class="bfa-activity-type">${activity.type}</span>
                            <span class="bfa-activity-object">${activity.object}</span>
                        `);
                        $list.append($item);
                    });
                },
                
                bindEvents: function() {
                    $('#bfa-pause-monitor').on('click', () => {
                        this.paused = !this.paused;
                        $('#bfa-pause-monitor').text(this.paused ? 'Resume' : 'Pause');
                        $('.bfa-status-dot').toggleClass('paused', this.paused);
                    });
                },
                
                refresh: function() {
                    this.fetchActivity();
                }
            };
            
            // Initialize activity monitor
            activityMonitor.init();
            window.bfaActivityMonitor = activityMonitor;
            
            // Generate test events
            $('#bfa-generate-test-events').on('click', function() {
                var $button = $(this);
                var $spinner = $('.bfa-test-actions .spinner');
                var $results = $('#bfa-test-results');
                
                $button.prop('disabled', true);
                $spinner.addClass('is-active');
                
                $.post(ajaxurl, {
                    action: 'bfa_generate_test_events',
                    nonce: '<?php echo wp_create_nonce('bfa_test_actions'); ?>'
                }, function(response) {
                    $button.prop('disabled', false);
                    $spinner.removeClass('is-active');
                    
                    if (response.success) {
                        $results.find('.notice')
                            .removeClass('notice-error')
                            .addClass('notice-success');
                        $results.find('p').html(response.data.message);
                        $results.show();
                        
                        // Refresh activity monitor
                        if (typeof window.bfaActivityMonitor !== 'undefined') {
                            window.bfaActivityMonitor.refresh();
                        }
                    } else {
                        $results.find('.notice')
                            .removeClass('notice-success')
                            .addClass('notice-error');
                        $results.find('p').text(response.data.message || 'An error occurred');
                        $results.show();
                    }
                });
            });
            
            // Clean test events
            $('#bfa-clean-test-events').on('click', function() {
                if (!confirm('<?php _e('Are you sure you want to delete all test data? This cannot be undone.', 'bandfront-analytics'); ?>')) {
                    return;
                }
                
                var $button = $(this);
                var $spinner = $('.bfa-test-actions .spinner');
                var $results = $('#bfa-test-results');
                
                $button.prop('disabled', true);
                $spinner.addClass('is-active');
                
                $.post(ajaxurl, {
                    action: 'bfa_clean_test_events',
                    nonce: '<?php echo wp_create_nonce('bfa_test_actions'); ?>'
                }, function(response) {
                    $button.prop('disabled', false);
                    $spinner.removeClass('is-active');
                    
                    if (response.success) {
                        $results.find('.notice')
                            .removeClass('notice-error')
                            .addClass('notice-success');
                        $results.find('p').html(response.data.message);
                        $results.show();
                        
                        // Refresh activity monitor
                        if (typeof window.bfaActivityMonitor !== 'undefined') {
                            window.bfaActivityMonitor.refresh();
                        }
                    } else {
                        $results.find('.notice')
                            .removeClass('notice-success')
                            .addClass('notice-error');
                        $results.find('p').text(response.data.message || 'An error occurred');
                        $results.show();
                    }
                });
            });
        });
        </script>
        <?php
    }
    
    /**
     * AJAX handler for generating test events
     */
    public function ajax_generate_test_events() {
        check_ajax_referer('bfa_test_actions', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Insufficient permissions', 'bandfront-analytics')]);
        }
        
        $result = DbTest::generateTestEvents(50); // Generate 50 test events
        
        if ($result['errors'] > 0) {
            wp_send_json_error([
                'message' => sprintf(
                    __('Generated %d test events with %d errors', 'bandfront-analytics'),
                    $result['generated'],
                    $result['errors']
                )
            ]);
        } else {
            wp_send_json_success([
                'message' => sprintf(
                    __('Successfully generated %d test events! <a href="#" onclick="location.reload()">Refresh page</a> to see them in the activity monitor.', 'bandfront-analytics'),
                    $result['generated']
                )
            ]);
        }
    }
    
    /**
     * AJAX handler for cleaning test events
     */
    public function ajax_clean_test_events() {
        check_ajax_referer('bfa_test_actions', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Insufficient permissions', 'bandfront-analytics')]);
        }
        
        $result = DbClean::cleanTestEvents();
        
        if ($result['success']) {
            wp_send_json_success([
                'message' => sprintf(
                    __('Successfully cleaned %d test events and %d test sessions! <a href="#" onclick="location.reload()">Refresh page</a> to update the display.', 'bandfront-analytics'),
                    $result['events_deleted'],
                    $result['sessions_cleaned']
                )
            ]);
        } else {
            wp_send_json_error([
                'message' => __('Failed to clean test data', 'bandfront-analytics')
            ]);
        }
    }
    
    /**
     * Render database statistics
     */
    private function renderDatabaseStats() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'bfa_events';
        
        // Get table stats
        $total_events = $wpdb->get_var("SELECT COUNT(*) FROM {$table_name}");
        $today_events = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table_name} WHERE DATE(created_at) = %s",
            current_time('Y-m-d')
        ));
        
        // Get table size
        $table_size = $wpdb->get_var($wpdb->prepare(
            "SELECT ROUND((data_length + index_length) / 1024 / 1024, 2) 
             FROM information_schema.TABLES 
             WHERE table_schema = %s AND table_name = %s",
            DB_NAME,
            $table_name
        ));
        
        ?>
        <div class="bfa-stats-grid">
            <div class="bfa-stat-item">
                <span class="bfa-stat-label"><?php _e('Total Events', 'bandfront-analytics'); ?></span>
                <span class="bfa-stat-value"><?php echo number_format($total_events); ?></span>
            </div>
            <div class="bfa-stat-item">
                <span class="bfa-stat-label"><?php _e('Today\'s Events', 'bandfront-analytics'); ?></span>
                <span class="bfa-stat-value"><?php echo number_format($today_events); ?></span>
            </div>
            <div class="bfa-stat-item">
                <span class="bfa-stat-label"><?php _e('Table Size', 'bandfront-analytics'); ?></span>
                <span class="bfa-stat-value"><?php echo $table_size; ?> MB</span>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render performance metrics
     */
    private function renderPerformanceMetrics() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'bfa_events';
        
        // Get average query time (simplified)
        $start = microtime(true);
        $wpdb->get_var("SELECT COUNT(*) FROM {$table_name} WHERE created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)");
        $query_time = round((microtime(true) - $start) * 1000, 2);
        
        // Get event rate
        $events_last_hour = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$table_name} WHERE created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)"
        );
        $events_per_minute = round($events_last_hour / 60, 2);
        
        ?>
        <div class="bfa-metric-item">
            <span class="bfa-metric-label"><?php _e('Avg Query Time', 'bandfront-analytics'); ?></span>
            <span class="bfa-metric-value"><?php echo $query_time; ?> ms</span>
        </div>
        <div class="bfa-metric-item">
            <span class="bfa-metric-label"><?php _e('Events/Minute', 'bandfront-analytics'); ?></span>
            <span class="bfa-metric-value"><?php echo $events_per_minute; ?></span>
        </div>
        <?php
    }
}