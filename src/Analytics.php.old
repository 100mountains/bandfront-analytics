<?php
namespace bfa;

if (!defined('ABSPATH')) {
    exit;
}

class Analytics {
    
    private Plugin $plugin;
    private array $trackedEvents = [];
    
    public function __construct(Plugin $plugin) {
        $this->plugin = $plugin;
        
        // Register tracking actions for other plugins to use
        add_action('bfa_track', [$this, 'track'], 10, 2);
        add_action('bfa_track_pageview', [$this, 'trackPageview'], 10, 1);
        add_action('bfa_track_music_play', [$this, 'trackMusicPlay'], 10, 2);
        add_action('bfa_track_music_complete', [$this, 'trackMusicComplete'], 10, 2);
        add_action('bfa_track_download', [$this, 'trackDownload'], 10, 2);
        
        // Register data retrieval filters
        add_filter('bfa_get_play_count', [$this, 'getPlayCount'], 10, 2);
        add_filter('bfa_get_post_views', [$this, 'getPostViews'], 10, 2);
        add_filter('bfa_get_trending', [$this, 'getTrending'], 10, 2);
        add_filter('bfa_get_quick_stats', [$this, 'getQuickStats'], 10, 1);
        
        // Auto-track WordPress events
        add_action('wp_login', [$this, 'trackUserLogin'], 10, 2);
        add_action('wp_logout', [$this, 'trackUserLogout']);
        add_action('comment_post', [$this, 'trackComment'], 10, 2);
        add_action('transition_post_status', [$this, 'trackPostStatus'], 10, 3);
        
        // WooCommerce integration (if active)
        if (class_exists('WooCommerce')) {
            add_action('woocommerce_thankyou', [$this, 'trackPurchase']);
            add_action('woocommerce_add_to_cart', [$this, 'trackAddToCart']);
        }
    }
    
    /**
     * Main tracking method
     */
    public function track(string $event, array $data = []): bool {
        // Prevent duplicate events in same request
        $eventKey = $event . '_' . md5(json_encode($data));
        if (isset($this->trackedEvents[$eventKey])) {
            return false;
        }
        $this->trackedEvents[$eventKey] = true;
        
        // Check if we should track
        if (!$this->shouldTrack()) {
            return false;
        }
        
        $eventData = [
            'event_type' => $event,
            'object_id' => $data['object_id'] ?? 0,
            'object_type' => $data['object_type'] ?? $this->detectObjectType($data['object_id'] ?? 0),
            'value' => $data['value'] ?? null,
            'meta_data' => $data['meta'] ?? [],
            'session_id' => $this->getSessionId(),
            'user_hash' => is_user_logged_in() ? md5(get_current_user_id()) : null,
            'referrer_domain' => $this->getReferrerDomain(),
            'user_agent_hash' => md5($_SERVER['HTTP_USER_AGENT'] ?? 'unknown'),
        ];
        
        return $this->plugin->getDatabase()->recordEvent($eventData);
    }
    
    /**
     * Track pageview
     */
    public function trackPageview(int $postId): bool {
        return $this->track('pageview', [
            'object_id' => $postId,
            'meta' => [
                'post_type' => get_post_type($postId),
                'author_id' => get_post_field('post_author', $postId),
            ]
        ]);
    }
    
    /**
     * Track music play
     */
    public function trackMusicPlay(int $trackId, int $productId = 0): bool {
        return $this->track('music_play', [
            'object_id' => $productId ?: $trackId,
            'meta' => [
                'track_id' => $trackId,
                'product_id' => $productId,
                'source' => current_filter(), // Track where it came from
            ]
        ]);
    }
    
    /**
     * Track music complete
     */
    public function trackMusicComplete(int $trackId, int $productId = 0): bool {
        return $this->track('music_complete', [
            'object_id' => $productId ?: $trackId,
            'value' => 100, // 100% completion
            'meta' => [
                'track_id' => $trackId,
                'product_id' => $productId,
            ]
        ]);
    }
    
    /**
     * Track download
     */
    public function trackDownload(int $fileId, int $productId = 0): bool {
        return $this->track('download', [
            'object_id' => $productId ?: $fileId,
            'meta' => [
                'file_id' => $fileId,
                'product_id' => $productId,
            ]
        ]);
    }
    
    /**
     * Get play count
     */
    public function getPlayCount(int $count, int $productId): int {
        return $this->plugin->getDatabase()->getProductPlayCount($productId);
    }
    
    /**
     * Get post views
     */
    public function getPostViews(int $views, int $postId): int {
        return $this->plugin->getDatabase()->getPostViews($postId);
    }
    
    /**
     * Get trending items
     */
    public function getTrending(array $items, array $args = []): array {
        $days = $args['days'] ?? 7;
        $limit = $args['limit'] ?? 10;
        $type = $args['type'] ?? 'all';
        
        return $this->plugin->getDatabase()->getTrending($days, $limit, $type);
    }
    
    /**
     * Get quick stats
     */
    public function getQuickStats(array $stats): array {
        $db = $this->plugin->getDatabase();
        
        return [
            'today_views' => $db->getTodayViews(),
            'today_plays' => $db->getTodayPlays(),
            'active_users' => $db->getActiveUsers(),
            'trending_count' => count($db->getTrending(1, 5)),
        ];
    }
    
    /**
     * Track user login
     */
    public function trackUserLogin(string $userLogin, \WP_User $user): void {
        $this->track('user_login', [
            'object_id' => $user->ID,
            'meta' => [
                'user_role' => implode(',', $user->roles),
            ]
        ]);
    }
    
    /**
     * Track user logout
     */
    public function trackUserLogout(): void {
        if (is_user_logged_in()) {
            $this->track('user_logout', [
                'object_id' => get_current_user_id(),
            ]);
        }
    }
    
    /**
     * Track comment
     */
    public function trackComment(int $commentId, $approved): void {
        if ($approved === 1) {
            $comment = get_comment($commentId);
            $this->track('comment', [
                'object_id' => $comment->comment_post_ID,
                'meta' => [
                    'comment_id' => $commentId,
                    'author' => $comment->comment_author,
                ]
            ]);
        }
    }
    
    /**
     * Track post status changes
     */
    public function trackPostStatus(string $new, string $old, \WP_Post $post): void {
        if ($new === 'publish' && $old !== 'publish') {
            $this->track('post_published', [
                'object_id' => $post->ID,
                'meta' => [
                    'post_type' => $post->post_type,
                    'author_id' => $post->post_author,
                ]
            ]);
        }
    }
    
    /**
     * Track WooCommerce purchase
     */
    public function trackPurchase(int $orderId): void {
        $order = wc_get_order($orderId);
        if (!$order) return;
        
        foreach ($order->get_items() as $item) {
            $this->track('purchase', [
                'object_id' => $item->get_product_id(),
                'value' => $item->get_total(),
                'meta' => [
                    'order_id' => $orderId,
                    'quantity' => $item->get_quantity(),
                ]
            ]);
        }
    }
    
    /**
     * Track add to cart
     */
    public function trackAddToCart(string $cartItemKey): void {
        $cart = WC()->cart->get_cart();
        if (isset($cart[$cartItemKey])) {
            $item = $cart[$cartItemKey];
            $this->track('add_to_cart', [
                'object_id' => $item['product_id'],
                'value' => $item['quantity'],
            ]);
        }
    }
    
    /**
     * Check if tracking should be enabled
     */
    private function shouldTrack(): bool {
        $config = $this->plugin->getConfig();
        
        // Check if tracking is enabled
        if (!$config->get('tracking_enabled', true)) {
            return false;
        }
        
        // Check admin exclusion
        if (is_user_logged_in() && current_user_can('manage_options') && $config->get('exclude_admins', true)) {
            return false;
        }
        
        // Check bot detection
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        if ($config->isBot($userAgent)) {
            return false;
        }
        
        // Check DNT
        if ($config->get('respect_dnt', true) && !empty($_SERVER['HTTP_DNT']) && $_SERVER['HTTP_DNT'] == '1') {
            return false;
        }
        
        return true;
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
    
    private function getReferrerDomain(): ?string {
        $referrer = wp_get_referer();
        return $referrer ? parse_url($referrer, PHP_URL_HOST) : null;
    }
    
    private function detectObjectType(int $objectId): string {
        if ($objectId === 0) return 'site';
        
        $postType = get_post_type($objectId);
        return $postType ?: 'unknown';
    }
}
