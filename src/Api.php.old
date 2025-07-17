<?php
namespace bfa;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Minimal REST API handler for JavaScript tracking and admin AJAX
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
        $namespace = 'bandfront-analytics/v1';
        
        // Single endpoint for JavaScript tracking
        register_rest_route($namespace, '/track', [
            'methods' => 'POST',
            'callback' => [$this, 'trackFromJavaScript'],
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
        
        // Keep minimal admin endpoints for AJAX widgets
        register_rest_route($namespace, '/admin/quick-stats', [
            'methods' => 'GET',
            'callback' => [$this, 'getQuickStats'],
            'permission_callback' => [$this, 'checkAdmin'],
        ]);
        
        // Chart data for admin dashboard
        register_rest_route($namespace, '/admin/chart', [
            'methods' => 'GET',
            'callback' => [$this, 'getChartData'],
            'permission_callback' => [$this, 'checkAdmin'],
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
    }
    
    /**
     * Handle tracking from JavaScript
     */
    public function trackFromJavaScript(\WP_REST_Request $request): \WP_REST_Response {
        // Pass to the main tracking system
        do_action('bfa_track', 
            $request->get_param('event_type'),
            $request->get_params()
        );
        
        return new \WP_REST_Response(['success' => true]);
    }
    
    /**
     * Get quick stats for admin bar
     */
    public function getQuickStats(): \WP_REST_Response {
        $stats = apply_filters('bfa_get_quick_stats', []);
        return new \WP_REST_Response($stats);
    }
    
    /**
     * Get chart data for admin dashboard
     */
    public function getChartData(\WP_REST_Request $request): \WP_REST_Response {
        $days = $request->get_param('days');
        $metric = $request->get_param('metric');
        
        $chartData = apply_filters('bfa_get_chart_data', [], $days, $metric);
        return new \WP_REST_Response($chartData);
    }
    
    /**
     * Check admin permission
     */
    public function checkAdmin(): bool {
        return current_user_can('manage_options');
    }
}