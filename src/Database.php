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
}
