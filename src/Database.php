<?php
namespace bfa;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Database handler for Bandfront Analytics
 */
class Database {
    
    private string $eventsTable;
    private string $statsTable;
    private \wpdb $wpdb;
    
    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->eventsTable = $wpdb->prefix . 'bfa_events';
        $this->statsTable = $wpdb->prefix . 'bfa_stats';
    }
    
    /**
     * Create database tables
     */
    public static function createTables(): void {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // Events table for raw tracking data
        $eventsTable = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}bfa_events (
            event_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            event_type VARCHAR(50) NOT NULL,
            object_id BIGINT UNSIGNED NOT NULL,
            object_type VARCHAR(50) DEFAULT 'post',
            user_hash VARCHAR(32) DEFAULT NULL,
            session_id VARCHAR(32) NOT NULL,
            timestamp DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            value FLOAT DEFAULT NULL,
            referrer_domain VARCHAR(255) DEFAULT NULL,
            user_agent_hash VARCHAR(32) DEFAULT NULL,
            meta_data JSON DEFAULT NULL,
            PRIMARY KEY (event_id),
            KEY idx_type_object (event_type, object_id),
            KEY idx_timestamp (timestamp),
            KEY idx_session (session_id)
        ) $charset_collate";
        
        // Aggregated stats table for performance
        $statsTable = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}bfa_stats (
            stat_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            stat_date DATE NOT NULL,
            stat_hour TINYINT DEFAULT NULL,
            object_id BIGINT UNSIGNED NOT NULL,
            object_type VARCHAR(50) DEFAULT 'post',
            metric_name VARCHAR(50) NOT NULL,
            metric_value BIGINT UNSIGNED NOT NULL DEFAULT 0,
            period_type ENUM('hourly', 'daily', 'monthly') DEFAULT 'daily',
            PRIMARY KEY (stat_id),
            UNIQUE KEY idx_unique_stat (stat_date, stat_hour, object_id, metric_name, period_type),
            KEY idx_date_metric (stat_date, metric_name),
            KEY idx_object (object_id, object_type)
        ) $charset_collate";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($eventsTable);
        dbDelta($statsTable);
    }
    
    /**
     * Record an event
     */
    public function recordEvent(array $data): bool {
        // Apply sampling if needed
        $config = $GLOBALS['BandfrontAnalytics']->getConfig();
        if ($config->shouldSample()) {
            return true; // Pretend success but don't record
        }
        
        // Prepare data
        $eventData = [
            'event_type' => $data['event_type'] ?? 'pageview',
            'object_id' => $data['object_id'] ?? 0,
            'object_type' => $data['object_type'] ?? 'post',
            'user_hash' => $data['user_hash'] ?? null,
            'session_id' => $data['session_id'] ?? '',
            'timestamp' => current_time('mysql'),
            'value' => $data['value'] ?? null,
            'referrer_domain' => $data['referrer_domain'] ?? null,
            'user_agent_hash' => $data['user_agent_hash'] ?? null,
            'meta_data' => !empty($data['meta_data']) ? json_encode($data['meta_data']) : null,
        ];
        
        // Add to batch queue
        $batch = get_transient('bfa_event_batch') ?: [];
        $batch[] = $eventData;
        
        $batchSize = $config->get('batch_size', 100);
        
        if (count($batch) >= $batchSize) {
            $this->flushEventBatch($batch);
            delete_transient('bfa_event_batch');
        } else {
            set_transient('bfa_event_batch', $batch, $config->get('realtime_timeout', 5));
        }
        
        return true;
    }
    
    /**
     * Flush event batch to database
     */
    public function flushEventBatch(array $batch): bool {
        if (empty($batch)) {
            return true;
        }
        
        $values = [];
        $placeholders = [];
        
        foreach ($batch as $event) {
            $placeholders[] = "(%s, %d, %s, %s, %s, %s, %f, %s, %s, %s)";
            
            array_push($values,
                $event['event_type'],
                $event['object_id'],
                $event['object_type'],
                $event['user_hash'],
                $event['session_id'],
                $event['timestamp'],
                $event['value'],
                $event['referrer_domain'],
                $event['user_agent_hash'],
                $event['meta_data']
            );
        }
        
        $query = "INSERT INTO {$this->eventsTable} 
                  (event_type, object_id, object_type, user_hash, session_id, timestamp, value, referrer_domain, user_agent_hash, meta_data) 
                  VALUES " . implode(', ', $placeholders);
        
        return $this->wpdb->query($this->wpdb->prepare($query, $values)) !== false;
    }
    
    /**
     * Get post views count
     */
    public function getPostViews(int $postId): int {
        $cache_key = 'bfa_post_views_' . $postId;
        $views = wp_cache_get($cache_key);
        
        if ($views === false) {
            $views = $this->wpdb->get_var($this->wpdb->prepare(
                "SELECT COALESCE(SUM(metric_value), 0) 
                 FROM {$this->statsTable} 
                 WHERE object_id = %d 
                 AND metric_name = 'pageviews' 
                 AND period_type = 'daily'",
                $postId
            ));
            
            wp_cache_set($cache_key, $views, '', 300); // 5 minute cache
        }
        
        return (int) $views;
    }
    
    /**
     * Get today's pageviews
     */
    public function getTodayPageviews(): int {
        $cache_key = 'bfa_today_pageviews';
        $views = wp_cache_get($cache_key);
        
        if ($views === false) {
            $today = current_time('Y-m-d');
            
            $views = $this->wpdb->get_var($this->wpdb->prepare(
                "SELECT COUNT(*) 
                 FROM {$this->eventsTable} 
                 WHERE event_type = 'pageview' 
                 AND DATE(timestamp) = %s",
                $today
            ));
            
            wp_cache_set($cache_key, $views, '', 60); // 1 minute cache
        }
        
        return (int) $views;
    }
    
    /**
     * Get quick stats for admin bar
     */
    public function getQuickStats(): array {
        $cache_key = 'bfa_quick_stats';
        $stats = wp_cache_get($cache_key);
        
        if ($stats === false) {
            $today = current_time('Y-m-d');
            $yesterday = date('Y-m-d', strtotime('-1 day', current_time('timestamp')));
            
            $stats = [
                'today_views' => $this->getTodayPageviews(),
                'yesterday_views' => $this->wpdb->get_var($this->wpdb->prepare(
                    "SELECT COUNT(*) FROM {$this->eventsTable} 
                     WHERE event_type = 'pageview' AND DATE(timestamp) = %s",
                    $yesterday
                )),
                'today_visitors' => $this->wpdb->get_var($this->wpdb->prepare(
                    "SELECT COUNT(DISTINCT session_id) FROM {$this->eventsTable} 
                     WHERE DATE(timestamp) = %s",
                    $today
                )),
                'active_users' => $this->wpdb->get_var(
                    "SELECT COUNT(DISTINCT session_id) FROM {$this->eventsTable} 
                     WHERE timestamp > DATE_SUB(NOW(), INTERVAL 5 MINUTE)"
                ),
                'today_plays' => $this->wpdb->get_var($this->wpdb->prepare(
                    "SELECT COUNT(*) FROM {$this->eventsTable} 
                     WHERE event_type = 'music_play' AND DATE(timestamp) = %s",
                    $today
                )),
            ];
            
            wp_cache_set($cache_key, $stats, '', 60);
        }
        
        return $stats;
    }
    
    /**
     * Get stats for date range
     */
    public function getStats(string $startDate, string $endDate, string $metric = 'pageviews'): array {
        $cache_key = "bfa_stats_{$startDate}_{$endDate}_{$metric}";
        $stats = wp_cache_get($cache_key);
        
        if ($stats === false) {
            $stats = $this->wpdb->get_results($this->wpdb->prepare(
                "SELECT stat_date, SUM(metric_value) as value 
                 FROM {$this->statsTable} 
                 WHERE stat_date BETWEEN %s AND %s 
                 AND metric_name = %s 
                 AND period_type = 'daily'
                 GROUP BY stat_date 
                 ORDER BY stat_date ASC",
                $startDate,
                $endDate,
                $metric
            ), ARRAY_A);
            
            wp_cache_set($cache_key, $stats, '', 300);
        }
        
        return $stats;
    }
    
    /**
     * Aggregate hourly stats
     */
    public function aggregateHourlyStats(): void {
        $lastHour = date('Y-m-d H:00:00', strtotime('-1 hour'));
        $hour = date('H', strtotime($lastHour));
        $date = date('Y-m-d', strtotime($lastHour));
        
        // Aggregate pageviews
        $this->wpdb->query($this->wpdb->prepare(
            "INSERT INTO {$this->statsTable} (stat_date, stat_hour, object_id, object_type, metric_name, metric_value, period_type)
             SELECT DATE(timestamp), HOUR(timestamp), object_id, object_type, 'pageviews', COUNT(*), 'hourly'
             FROM {$this->eventsTable}
             WHERE event_type = 'pageview'
             AND timestamp >= %s
             AND timestamp < DATE_ADD(%s, INTERVAL 1 HOUR)
             GROUP BY object_id, object_type
             ON DUPLICATE KEY UPDATE metric_value = VALUES(metric_value)",
            $lastHour,
            $lastHour
        ));
        
        // Clear caches
        wp_cache_flush();
    }
    
    /**
     * Clean up old data
     */
    public function cleanupOldData(): void {
        $config = $GLOBALS['BandfrontAnalytics']->getConfig();
        $retentionDays = $config->get('retention_days', 365);
        
        $cutoffDate = date('Y-m-d', strtotime("-{$retentionDays} days"));
        
        // Delete old events
        $this->wpdb->query($this->wpdb->prepare(
            "DELETE FROM {$this->eventsTable} WHERE timestamp < %s",
            $cutoffDate
        ));
        
        // Delete old stats
        $this->wpdb->query($this->wpdb->prepare(
            "DELETE FROM {$this->statsTable} WHERE stat_date < %s",
            $cutoffDate
        ));
    }
    
    /**
     * Get top posts for a period
     */
    public function getTopPosts(int $limit = 10, int $days = 7): array {
        $cache_key = "bfa_top_posts_{$limit}_{$days}";
        $posts = wp_cache_get($cache_key);
        
        if ($posts === false) {
            $startDate = date('Y-m-d', strtotime("-{$days} days"));
            
            $posts = $this->wpdb->get_results($this->wpdb->prepare(
                "SELECT object_id, COUNT(*) as views 
                 FROM {$this->eventsTable} 
                 WHERE event_type = 'pageview' 
                 AND object_type IN ('post', 'page')
                 AND timestamp >= %s
                 GROUP BY object_id 
                 ORDER BY views DESC 
                 LIMIT %d",
                $startDate,
                $limit
            ), ARRAY_A);
            
            wp_cache_set($cache_key, $posts, '', 300);
        }
        
        return $posts;
    }
    
    /**
     * Get music play stats
     */
    public function getMusicStats(string $startDate, string $endDate): array {
        $cache_key = "bfa_music_stats_{$startDate}_{$endDate}";
        $stats = wp_cache_get($cache_key);
        
        if ($stats === false) {
            $stats = [
                'total_plays' => $this->wpdb->get_var($this->wpdb->prepare(
                    "SELECT COUNT(*) FROM {$this->eventsTable} 
                     WHERE event_type = 'music_play' 
                     AND timestamp BETWEEN %s AND %s",
                    $startDate . ' 00:00:00',
                    $endDate . ' 23:59:59'
                )),
                'unique_tracks' => $this->wpdb->get_var($this->wpdb->prepare(
                    "SELECT COUNT(DISTINCT object_id) FROM {$this->eventsTable} 
                     WHERE event_type = 'music_play' 
                     AND timestamp BETWEEN %s AND %s",
                    $startDate . ' 00:00:00',
                    $endDate . ' 23:59:59'
                )),
                'avg_duration' => $this->wpdb->get_var($this->wpdb->prepare(
                    "SELECT AVG(value) FROM {$this->eventsTable} 
                     WHERE event_type = 'music_duration' 
                     AND timestamp BETWEEN %s AND %s",
                    $startDate . ' 00:00:00',
                    $endDate . ' 23:59:59'
                )),
            ];
            
            wp_cache_set($cache_key, $stats, '', 300);
        }
        
        return $stats;
    }
    
    /**
     * Aggregate daily stats
     */
    public function aggregateDailyStats(): void {
        $yesterday = date('Y-m-d', strtotime('-1 day'));
        
        // Aggregate by object and metric
        $metrics = ['pageviews', 'music_plays'];
        
        foreach ($metrics as $metric) {
            $eventType = $metric === 'pageviews' ? 'pageview' : 'music_play';
            
            $this->wpdb->query($this->wpdb->prepare(
                "INSERT INTO {$this->statsTable} (stat_date, object_id, object_type, metric_name, metric_value, period_type)
                 SELECT DATE(timestamp), object_id, object_type, %s, COUNT(*), 'daily'
                 FROM {$this->eventsTable}
                 WHERE event_type = %s
                 AND DATE(timestamp) = %s
                 GROUP BY object_id, object_type
                 ON DUPLICATE KEY UPDATE metric_value = VALUES(metric_value)",
                $metric,
                $eventType,
                $yesterday
            ));
        }
        
        // Also create site-wide daily totals
        $this->wpdb->query($this->wpdb->prepare(
            "INSERT INTO {$this->statsTable} (stat_date, object_id, object_type, metric_name, metric_value, period_type)
             SELECT %s, 0, 'site', 'total_pageviews', COUNT(*), 'daily'
             FROM {$this->eventsTable}
             WHERE event_type = 'pageview'
             AND DATE(timestamp) = %s
             ON DUPLICATE KEY UPDATE metric_value = VALUES(metric_value)",
            $yesterday,
            $yesterday
        ));
    }
    
    /**
     * Get real-time active users
     */
    public function getActiveUsers(int $minutes = 5): array {
        $since = date('Y-m-d H:i:s', strtotime("-{$minutes} minutes"));
        
        return $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT DISTINCT session_id, MAX(timestamp) as last_seen,
             COUNT(*) as page_views
             FROM {$this->eventsTable}
             WHERE timestamp > %s
             GROUP BY session_id
             ORDER BY last_seen DESC",
            $since
        ), ARRAY_A);
    }
    
    /**
     * Get top played tracks
     */
    public function getTopTracks(int $limit = 10, int $days = 7): array {
        $cache_key = "bfa_top_tracks_{$limit}_{$days}";
        $tracks = wp_cache_get($cache_key);
        
        if ($tracks === false) {
            $startDate = date('Y-m-d', strtotime("-{$days} days"));
            
            $tracks = $this->wpdb->get_results($this->wpdb->prepare(
                "SELECT 
                    e.object_id,
                    COUNT(*) as plays,
                    AVG(CASE WHEN d.event_type = 'music_duration' THEN d.value ELSE NULL END) as avg_duration
                 FROM {$this->eventsTable} e
                 LEFT JOIN {$this->eventsTable} d ON e.session_id = d.session_id 
                    AND e.object_id = d.object_id 
                    AND d.event_type = 'music_duration'
                 WHERE e.event_type = 'music_play' 
                 AND e.timestamp >= %s
                 GROUP BY e.object_id 
                 ORDER BY plays DESC 
                 LIMIT %d",
                $startDate,
                $limit
            ), ARRAY_A);
            
            // Enhance with post data
            foreach ($tracks as &$track) {
                $postObj = get_post($track['object_id']);
                if ($postObj) {
                    $track['title'] = $postObj->post_title;
                    $track['url'] = get_permalink($postObj);
                    $track['type'] = $postObj->post_type;
                } else {
                    $track['title'] = __('Unknown Track', 'bandfront-analytics');
                    $track['url'] = '#';
                    $track['type'] = 'unknown';
                }
                
                // Format duration
                $avgDuration = floatval($track['avg_duration']);
                $track['avg_duration'] = $avgDuration > 0 ? gmdate("i:s", $avgDuration) : '--:--';
            }
            
            wp_cache_set($cache_key, $tracks, '', 300);
        }
        
        return $tracks;
    }
    
    /**
     * Get member stats
     */
    public function getMemberStats(string $startDate, string $endDate): array {
        $cache_key = "bfa_member_stats_{$startDate}_{$endDate}";
        $stats = wp_cache_get($cache_key);
        
        if ($stats === false) {
            // Check if Bandfront Members plugin is active
            if (class_exists('BandfrontMembers')) {
                // Real stats from member plugin integration
                $stats = [
                    'total_members' => $this->getTotalMembers(),
                    'new_members_week' => $this->getNewMembersCount(7),
                    'active_members' => $this->getActiveMembers(),
                    'engagement_rate' => $this->getMemberEngagementRate(),
                ];
            } else {
                // Placeholder stats
                $stats = [
                    'total_members' => 0,
                    'new_members_week' => 0,
                    'active_members' => 0,
                    'engagement_rate' => 0,
                ];
            }
            
            wp_cache_set($cache_key, $stats, '', 300);
        }
        
        return $stats;
    }
    
    /**
     * Get total members count
     */
    private function getTotalMembers(): int {
        // Get users with the configured backer role
        $backer_role = get_option('bfm_settings')['backer_role'] ?? 'subscriber';
        
        $args = [
            'role' => $backer_role,
            'count_total' => true,
        ];
        
        $user_query = new \WP_User_Query($args);
        return $user_query->get_total();
    }
    
    /**
     * Get new members in last N days
     */
    private function getNewMembersCount(int $days): int {
        $backer_role = get_option('bfm_settings')['backer_role'] ?? 'subscriber';
        
        $args = [
            'role' => $backer_role,
            'date_query' => [
                [
                    'after' => $days . ' days ago',
                    'inclusive' => true,
                ],
            ],
            'count_total' => true,
        ];
        
        $user_query = new \WP_User_Query($args);
        return $user_query->get_total();
    }
    
    /**
     * Get active members (logged in within 30 days)
     */
    private function getActiveMembers(): int {
        $backer_role = get_option('bfm_settings')['backer_role'] ?? 'subscriber';
        
        $args = [
            'role' => $backer_role,
            'meta_query' => [
                [
                    'key' => 'last_login',
                    'value' => date('Y-m-d H:i:s', strtotime('-30 days')),
                    'compare' => '>',
                    'type' => 'DATETIME'
                ],
            ],
            'count_total' => true,
        ];
        
        $user_query = new \WP_User_Query($args);
        return $user_query->get_total();
    }
    
    /**
     * Calculate member engagement rate
     */
    private function getMemberEngagementRate(): int {
        $total = $this->getTotalMembers();
        if ($total === 0) return 0;
        
        $active = $this->getActiveMembers();
        return round(($active / $total) * 100);
    }
    
    /**
     * Get play count for a specific product
     */
    public function getProductPlayCount(int $productId): int {
        $cache_key = 'bfa_product_plays_' . $productId;
        $plays = wp_cache_get($cache_key);
        
        if ($plays === false) {
            $plays = $this->wpdb->get_var($this->wpdb->prepare(
                "SELECT COUNT(*) 
                 FROM {$this->eventsTable} 
                 WHERE object_id = %d 
                 AND event_type = 'music_play'",
                $productId
            ));
            
            wp_cache_set($cache_key, $plays, '', 300); // 5 minute cache
        }
        
        return (int) $plays;
    }
    
    /**
     * Update cached play counts for products
     * Called periodically to refresh meta values
     */
    public function updateProductPlayCounts(): void {
        // Get all products with play events
        $products = $this->wpdb->get_results(
            "SELECT object_id, COUNT(*) as play_count 
             FROM {$this->eventsTable} 
             WHERE event_type = 'music_play' 
             AND object_type = 'product'
             GROUP BY object_id"
        );
        
        foreach ($products as $product) {
            update_post_meta($product->object_id, '_bfa_play_counter', $product->play_count);
        }
        
        // Also update view counts for all posts
        $views = $this->wpdb->get_results(
            "SELECT object_id, COUNT(*) as view_count 
             FROM {$this->eventsTable} 
             WHERE event_type = 'pageview' 
             GROUP BY object_id"
        );
        
        foreach ($views as $view) {
            update_post_meta($view->object_id, '_bfa_total_views', $view->view_count);
        }
    }
    
    /**
     * Get user login stats
     */
    public function getUserLoginStats(int $userId = 0): array {
        $cache_key = 'bfa_user_login_stats_' . $userId;
        $stats = wp_cache_get($cache_key);
        
        if ($stats === false) {
            if ($userId > 0) {
                // Stats for specific user
                $stats = [
                    'total_logins' => $this->wpdb->get_var($this->wpdb->prepare(
                        "SELECT COUNT(*) FROM {$this->eventsTable} 
                         WHERE event_type = 'user_login' 
                         AND object_id = %d",
                        $userId
                    )),
                    'last_login' => $this->wpdb->get_var($this->wpdb->prepare(
                        "SELECT MAX(timestamp) FROM {$this->eventsTable} 
                         WHERE event_type = 'user_login' 
                         AND object_id = %d",
                        $userId
                    )),
                ];
            } else {
                // Site-wide stats
                $stats = [
                    'total_logins_today' => $this->wpdb->get_var($this->wpdb->prepare(
                        "SELECT COUNT(*) FROM {$this->eventsTable} 
                         WHERE event_type = 'user_login' 
                         AND DATE(timestamp) = %s",
                        current_time('Y-m-d')
                    )),
                    'unique_users_today' => $this->wpdb->get_var($this->wpdb->prepare(
                        "SELECT COUNT(DISTINCT object_id) FROM {$this->eventsTable} 
                         WHERE event_type = 'user_login' 
                         AND DATE(timestamp) = %s",
                        current_time('Y-m-d')
                    )),
                    'active_users_hour' => $this->wpdb->get_var(
                        "SELECT COUNT(DISTINCT object_id) FROM {$this->eventsTable} 
                         WHERE event_type = 'user_login' 
                         AND timestamp > DATE_SUB(NOW(), INTERVAL 1 HOUR)"
                    ),
                ];
            }
            
            wp_cache_set($cache_key, $stats, '', 300);
        }
        
        return $stats;
    }
    
    /**
     * Get most active users
     */
    public function getMostActiveUsers(int $limit = 10, int $days = 30): array {
        $cache_key = "bfa_most_active_users_{$limit}_{$days}";
        $users = wp_cache_get($cache_key);
        
        if ($users === false) {
            $startDate = date('Y-m-d', strtotime("-{$days} days"));
            
            $users = $this->wpdb->get_results($this->wpdb->prepare(
                "SELECT 
                    object_id as user_id,
                    COUNT(*) as login_count,
                    MAX(timestamp) as last_login
                 FROM {$this->eventsTable} 
                 WHERE event_type = 'user_login' 
                 AND timestamp >= %s
                 GROUP BY object_id 
                 ORDER BY login_count DESC 
                 LIMIT %d",
                $startDate,
                $limit
            ), ARRAY_A);
            
            // Enhance with user data
            foreach ($users as &$user) {
                $userObj = get_user_by('id', $user['user_id']);
                if ($userObj) {
                    $user['display_name'] = $userObj->display_name;
                    $user['email'] = $userObj->user_email;
                    $user['roles'] = implode(', ', $userObj->roles);
                }
            }
            
            wp_cache_set($cache_key, $users, '', 300);
        }
        
        return $users;
    }
    
    /**
     * Get user activity timeline
     */
    public function getUserActivityTimeline(int $userId, int $days = 7): array {
        $cache_key = "bfa_user_activity_{$userId}_{$days}";
        $activity = wp_cache_get($cache_key);
        
        if ($activity === false) {
            $startDate = date('Y-m-d', strtotime("-{$days} days"));
            
            $activity = $this->wpdb->get_results($this->wpdb->prepare(
                "SELECT 
                    DATE(timestamp) as activity_date,
                    COUNT(CASE WHEN event_type = 'pageview' THEN 1 END) as pageviews,
                    COUNT(CASE WHEN event_type = 'music_play' THEN 1 END) as music_plays,
                    COUNT(CASE WHEN event_type = 'user_login' THEN 1 END) as logins
                 FROM {$this->eventsTable} 
                 WHERE object_id = %d 
                 AND object_type = 'user'
                 AND timestamp >= %s
                 GROUP BY activity_date 
                 ORDER BY activity_date ASC",
                $userId,
                $startDate
            ), ARRAY_A);
            
            wp_cache_set($cache_key, $activity, '', 300);
        }
        
        return $activity;
    }
}
