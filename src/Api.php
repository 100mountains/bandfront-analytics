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
    }
    
    /**
     * Register REST API routes
     */
    public function registerRoutes(): void {
        // Track event endpoint
        register_rest_route('bandfront-analytics/v1', '/track', [
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
        register_rest_route('bandfront-analytics/v1', '/stats', [
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
        register_rest_route('bandfront-analytics/v1', '/quick-stats', [
            'methods' => 'GET',
            'callback' => [$this, 'getQuickStats'],
            'permission_callback' => [$this, 'checkStatsPermission'],
        ]);
        
        // Chart data endpoint
        register_rest_route('bandfront-analytics/v1', '/chart', [
            'methods' => 'GET',
            'callback' => [$this, 'getChartData'],
            'permission_callback' => [$this, 'checkStatsPermission'],
            'args' => [
                'days' => [
                    'type' => 'integer',
                    'default' => 7,
                ],
            ],
        ]);
        
        // Top posts endpoint
        register_rest_route('bandfront-analytics/v1', '/top-posts', [
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
        $endDate = date('Y-m-d');
        $startDate = date('Y-m-d', strtotime("-{$days} days"));
        
        $stats = $this->plugin->getDatabase()->getStats($startDate, $endDate, 'pageviews');
        
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
        
        return new \WP_REST_Response([
            'labels' => $labels,
            'datasets' => [
                [
                    'label' => __('Page Views', 'bandfront-analytics'),
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
}
