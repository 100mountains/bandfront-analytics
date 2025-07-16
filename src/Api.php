<?php
namespace bfa;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * REST API handler for analytics
 */
class Api {
    
    private Plugin $plugin;
    
    public function __construct(Plugin $plugin) {
        $this->plugin = $plugin;
        
        // Add REST request tracking
        add_filter('rest_pre_dispatch', [$this, 'trackRestRequest'], 10, 3);
    }
    
    /**
     * Register REST API routes
     */
    public function registerRoutes(): void {
        // Check if API is enabled
        if (!$this->plugin->getConfig()->get('enable_api', true)) {
            return;
        }
        
        $namespace = 'bandfront-analytics/v1';
        
        // Track event endpoint
        register_rest_route($namespace, '/track', [
            'methods' => 'POST',
            'callback' => [$this, 'trackEvent'],
            'permission_callback' => '__return_true',
            'args' => [
                'event_type' => [
                    'required' => true,
                    'type' => 'string',
                ],
                'object_id' => [
                    'type' => 'integer',
                    'default' => 0,
                ],
                'value' => [
                    'type' => 'number',
                ],
                'meta' => [
                    'type' => 'object',
                ],
            ],
        ]);
        
        // Stats endpoint
        register_rest_route($namespace, '/stats', [
            'methods' => 'GET',
            'callback' => [$this, 'getStats'],
            'permission_callback' => [$this, 'checkStatsPermission'],
            'args' => [
                'start_date' => [
                    'type' => 'string',
                    'format' => 'date',
                ],
                'end_date' => [
                    'type' => 'string',
                    'format' => 'date',
                ],
                'metric' => [
                    'type' => 'string',
                    'default' => 'pageviews',
                ],
            ],
        ]);
        
        // Quick stats endpoint
        register_rest_route($namespace, '/quick-stats', [
            'methods' => 'GET',
            'callback' => [$this, 'getQuickStats'],
            'permission_callback' => [$this, 'checkStatsPermission'],
        ]);
        
        // Chart data endpoint
        register_rest_route($namespace, '/chart', [
            'methods' => 'GET',
            'callback' => [$this, 'getChartData'],
            'permission_callback' => [$this, 'checkStatsPermission'],
            'args' => [
                'days' => [
                    'type' => 'integer',
                    'default' => 7,
                ],
                'metric' => [
                    'type' => 'string',
                    'default' => 'pageviews',
                ],
            ],
        ]);
        
        // Top posts endpoint
        register_rest_route($namespace, '/top-posts', [
            'methods' => 'GET',
            'callback' => [$this, 'getTopPosts'],
            'permission_callback' => [$this, 'checkStatsPermission'],
            'args' => [
                'limit' => [
                    'type' => 'integer',
                    'default' => 10,
                ],
                'days' => [
                    'type' => 'integer',
                    'default' => 7,
                ],
            ],
        ]);
        
        // Top tracks endpoint
        register_rest_route($namespace, '/top-tracks', [
            'methods' => 'GET',
            'callback' => [$this, 'getTopTracks'],
            'permission_callback' => [$this, 'checkStatsPermission'],
            'args' => [
                'limit' => [
                    'type' => 'integer',
                    'default' => 10,
                ],
                'days' => [
                    'type' => 'integer',
                    'default' => 7,
                ],
            ],
        ]);
        
        // Music stats endpoint
        register_rest_route($namespace, '/music-stats', [
            'methods' => 'GET',
            'callback' => [$this, 'getMusicStats'],
            'permission_callback' => [$this, 'checkStatsPermission'],
            'args' => [
                'start_date' => [
                    'type' => 'string',
                    'format' => 'date',
                ],
                'end_date' => [
                    'type' => 'string',
                    'format' => 'date',
                ],
            ],
        ]);
        
        // Active users endpoint
        register_rest_route($namespace, '/active-users', [
            'methods' => 'GET',
            'callback' => [$this, 'getActiveUsers'],
            'permission_callback' => [$this, 'checkStatsPermission'],
            'args' => [
                'minutes' => [
                    'type' => 'integer',
                    'default' => 5,
                ],
            ],
        ]);
        
        // Member stats endpoints
        register_rest_route($namespace, '/member-stats', [
            'methods' => 'GET',
            'callback' => [$this, 'getMemberStats'],
            'permission_callback' => [$this, 'checkStatsPermission'],
        ]);
        
        register_rest_route($namespace, '/member-growth', [
            'methods' => 'GET',
            'callback' => [$this, 'getMemberGrowth'],
            'permission_callback' => [$this, 'checkStatsPermission'],
            'args' => [
                'days' => [
                    'type' => 'integer',
                    'default' => 7,
                ],
            ],
        ]);
        
        register_rest_route($namespace, '/member-activity', [
            'methods' => 'GET',
            'callback' => [$this, 'getMemberActivity'],
            'permission_callback' => [$this, 'checkStatsPermission'],
            'args' => [
                'days' => [
                    'type' => 'integer',
                    'default' => 7,
                ],
                'limit' => [
                    'type' => 'integer',
                    'default' => 10,
                ],
            ],
        ]);
        
        // User login stats endpoint
        register_rest_route($namespace, '/user-logins', [
            'methods' => 'GET',
            'callback' => [$this, 'getUserLoginStats'],
            'permission_callback' => [$this, 'checkStatsPermission'],
            'args' => [
                'user_id' => [
                    'type' => 'integer',
                    'default' => 0,
                ],
            ],
        ]);
    }
    
    /**
     * Track an event via API
     */
    public function trackEvent(\WP_REST_Request $request): \WP_REST_Response {
        $eventType = $request->get_param('event_type');
        $objectId = $request->get_param('object_id');
        $value = $request->get_param('value');
        $meta = $request->get_param('meta');
        
        // Generate session ID
        $sessionId = $this->getSessionId();
        
        // Hash user data for privacy
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $userAgentHash = md5($userAgent);
        
        // Get referrer domain
        $referrer = wp_get_referer();
        $referrerDomain = $referrer ? parse_url($referrer, PHP_URL_HOST) : null;
        
        // Record event
        $success = $this->plugin->getDatabase()->recordEvent([
            'event_type' => $eventType,
            'object_id' => $objectId,
            'object_type' => get_post_type($objectId) ?: 'unknown',
            'session_id' => $sessionId,
            'value' => $value,
            'referrer_domain' => $referrerDomain,
            'user_agent_hash' => $userAgentHash,
            'meta_data' => $meta,
        ]);
        
        return new \WP_REST_Response([
            'success' => $success,
            'session_id' => $sessionId,
        ]);
    }
    
    /**
     * Get stats via API
     */
    public function getStats(\WP_REST_Request $request): \WP_REST_Response {
        $startDate = $request->get_param('start_date') ?: date('Y-m-d', strtotime('-7 days'));
        $endDate = $request->get_param('end_date') ?: date('Y-m-d');
        $metric = $request->get_param('metric');
        
        $stats = $this->plugin->getDatabase()->getStats($startDate, $endDate, $metric);
        
        return new \WP_REST_Response([
            'stats' => $stats,
            'period' => [
                'start' => $startDate,
                'end' => $endDate,
            ],
            'metric' => $metric,
        ]);
    }
    
    /**
     * Get quick stats
     */
    public function getQuickStats(): \WP_REST_Response {
        $stats = $this->plugin->getDatabase()->getQuickStats();
        
        return new \WP_REST_Response($stats);
    }
    
    /**
     * Get chart data for admin bar
     */
    public function getChartData(\WP_REST_Request $request): \WP_REST_Response {
        $days = $request->get_param('days');
        $metric = $request->get_param('metric') ?: 'pageviews';
        $endDate = date('Y-m-d');
        $startDate = date('Y-m-d', strtotime("-{$days} days"));
        
        $stats = $this->plugin->getDatabase()->getStats($startDate, $endDate, $metric);
        
        // Format for Chart.js
        $labels = [];
        $data = [];
        
        // Create date range
        $period = new \DatePeriod(
            new \DateTime($startDate),
            new \DateInterval('P1D'),
            (new \DateTime($endDate))->modify('+1 day')
        );
        
        // Build date map
        $dateMap = [];
        foreach ($stats as $stat) {
            $dateMap[$stat['stat_date']] = (int) $stat['value'];
        }
        
        // Fill in missing dates with 0
        foreach ($period as $date) {
            $dateStr = $date->format('Y-m-d');
            $labels[] = $date->format('M j');
            $data[] = $dateMap[$dateStr] ?? 0;
        }
        
        $label = $metric === 'music_plays' ? __('Music Plays', 'bandfront-analytics') : __('Page Views', 'bandfront-analytics');
        
        return new \WP_REST_Response([
            'labels' => $labels,
            'datasets' => [
                [
                    'label' => $label,
                    'data' => $data,
                    'borderColor' => '#0073aa',
                    'backgroundColor' => 'rgba(0, 115, 170, 0.1)',
                    'tension' => 0.1,
                ],
            ],
        ]);
    }
    
    /**
     * Get top posts
     */
    public function getTopPosts(\WP_REST_Request $request): \WP_REST_Response {
        $limit = $request->get_param('limit');
        $days = $request->get_param('days');
        
        $topPosts = $this->plugin->getDatabase()->getTopPosts($limit, $days);
        
        // Enhance with post data
        foreach ($topPosts as &$post) {
            $postObj = get_post($post['object_id']);
            if ($postObj) {
                $post['title'] = $postObj->post_title;
                $post['url'] = get_permalink($postObj);
                $post['type'] = $postObj->post_type;
            }
        }
        
        return new \WP_REST_Response($topPosts);
    }
    
    /**
     * Get top tracks
     */
    public function getTopTracks(\WP_REST_Request $request): \WP_REST_Response {
        $limit = $request->get_param('limit');
        $days = $request->get_param('days');
        
        $topTracks = $this->plugin->getDatabase()->getTopTracks($limit, $days);
        
        return new \WP_REST_Response($topTracks);
    }
    
    /**
     * Get music stats
     */
    public function getMusicStats(\WP_REST_Request $request): \WP_REST_Response {
        $startDate = $request->get_param('start_date') ?: date('Y-m-d', strtotime('-7 days'));
        $endDate = $request->get_param('end_date') ?: date('Y-m-d');
        
        $stats = $this->plugin->getDatabase()->getMusicStats($startDate, $endDate);
        
        return new \WP_REST_Response($stats);
    }
    
    /**
     * Get active users
     */
    public function getActiveUsers(\WP_REST_Request $request): \WP_REST_Response {
        $minutes = $request->get_param('minutes');
        
        $users = $this->plugin->getDatabase()->getActiveUsers($minutes);
        
        return new \WP_REST_Response([
            'count' => count($users),
            'users' => $users,
        ]);
    }
    
    /**
     * Get member stats
     */
    public function getMemberStats(\WP_REST_Request $request): \WP_REST_Response {
        $startDate = date('Y-m-d', strtotime('-7 days'));
        $endDate = date('Y-m-d');
        
        $stats = $this->plugin->getDatabase()->getMemberStats($startDate, $endDate);
        
        return new \WP_REST_Response($stats);
    }
    
    /**
     * Get user login stats
     */
    public function getUserLoginStats(\WP_REST_Request $request): \WP_REST_Response {
        $userId = $request->get_param('user_id');
        
        $stats = $this->plugin->getDatabase()->getUserLoginStats($userId);
        
        return new \WP_REST_Response($stats);
    }
    
    /**
     * Get member growth data
     */
    public function getMemberGrowth(\WP_REST_Request $request): \WP_REST_Response {
        $days = $request->get_param('days');
        
        // Check if Bandfront Members is active
        if (!class_exists('BandfrontMembers')) {
            return new \WP_REST_Response([
                'error' => 'Members plugin not active',
                'labels' => [],
                'datasets' => []
            ], 200);
        }
        
        // Generate sample data for now
        $labels = [];
        $newMembers = [];
        $totalMembers = [];
        
        $baseTotal = 150; // Starting number
        
        for ($i = $days; $i >= 0; $i--) {
            $date = date('M j', strtotime("-{$i} days"));
            $labels[] = $date;
            
            // Simulate growth
            $dailyNew = rand(0, 5);
            $newMembers[] = $dailyNew;
            $baseTotal += $dailyNew;
            $totalMembers[] = $baseTotal;
        }
        
        return new \WP_REST_Response([
            'labels' => $labels,
            'datasets' => [
                [
                    'label' => __('Total Members', 'bandfront-analytics'),
                    'data' => $totalMembers,
                    'borderColor' => '#0073aa',
                    'backgroundColor' => 'rgba(0, 115, 170, 0.1)',
                    'tension' => 0.1,
                ],
                [
                    'label' => __('New Members', 'bandfront-analytics'),
                    'data' => $newMembers,
                    'borderColor' => '#46b450',
                    'backgroundColor' => 'rgba(70, 180, 80, 0.1)',
                    'tension' => 0.1,
                    'yAxisID' => 'y1',
                ],
            ],
        ]);
    }
    
    /**
     * Get member activity
     */
    public function getMemberActivity(\WP_REST_Request $request): \WP_REST_Response {
        $days = $request->get_param('days');
        $limit = $request->get_param('limit');
        
        // Check if Bandfront Members is active
        if (!class_exists('BandfrontMembers')) {
            return new \WP_REST_Response([], 404);
        }
        
        // This would integrate with the member plugin
        // For now, return empty array
        return new \WP_REST_Response([]);
    }
    
    /**
     * Check permission for viewing stats
     */
    public function checkStatsPermission(): bool {
        return current_user_can('manage_options');
    }
    
    /**
     * Get or create session ID
     */
    private function getSessionId(): string {
        if (!session_id()) {
            session_start();
        }
        
        if (!isset($_SESSION['bfa_session_id'])) {
            $_SESSION['bfa_session_id'] = md5(uniqid('bfa_', true));
        }
        
        return $_SESSION['bfa_session_id'];
    }
    
    /**
     * Track REST API requests
     */
    public function trackRestRequest($result, $server, $request) {
        // Only track our namespace
        $route = $request->get_route();
        if (strpos($route, '/bandfront-analytics/') !== 0) {
            return $result;
        }
        
        // Only track if API monitoring is enabled
        if (!$this->plugin->getConfig()->get('enable_api', true)) {
            return $result;
        }
        
        // Get existing traffic
        $traffic = get_transient('bfa_api_traffic') ?: [];
        
        // Add new request
        $traffic[] = [
            'timestamp' => current_time('mysql'),
            'method' => $request->get_method(),
            'route' => $route,
            'params' => $request->get_params(),
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'user_id' => get_current_user_id(),
        ];
        
        // Keep only last 100 requests
        if (count($traffic) > 100) {
            $traffic = array_slice($traffic, -100);
        }
        
        // Save for 1 hour
        set_transient('bfa_api_traffic', $traffic, HOUR_IN_SECONDS);
        
        return $result;
    }
}
