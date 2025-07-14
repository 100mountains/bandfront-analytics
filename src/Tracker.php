<?php
namespace bfa;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Frontend tracking handler
 */
class Tracker {
    
    private Plugin $plugin;
    
    public function __construct(Plugin $plugin) {
        $this->plugin = $plugin;
    }
    
    /**
     * Output tracking data in footer
     */
    public function outputTrackingData(): void {
        if (!$this->shouldTrack()) {
            return;
        }
        
        global $post;
        
        $trackingData = [
            'pageType' => $this->getPageType(),
            'objectId' => is_singular() ? get_the_ID() : 0,
            'objectType' => is_singular() ? get_post_type() : 'archive',
        ];
        
        // Add music tracking data if on a product page
        if (function_exists('is_product') && is_product()) {
            $trackingData['hasMusicPlayer'] = $this->hasAudioFiles($post->ID);
        }
        
        ?>
        <script type="text/javascript">
            window.bfaPageData = <?php echo wp_json_encode($trackingData); ?>;
        </script>
        <?php
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
        
        // Check DNT header
        if ($config->get('respect_dnt', true) && !empty($_SERVER['HTTP_DNT']) && $_SERVER['HTTP_DNT'] == '1') {
            return false;
        }
        
        // Check if user agent is a bot
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        if ($config->isBot($userAgent)) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Determine the current page type
     */
    private function getPageType(): string {
        if (is_front_page()) return 'home';
        if (is_single()) return 'single';
        if (is_page()) return 'page';
        if (is_archive()) return 'archive';
        if (is_search()) return 'search';
        if (is_404()) return '404';
        
        return 'other';
    }
    
    /**
     * Check if post has audio files
     */
    private function hasAudioFiles(int $postId): bool {
        // Check for WooCommerce product audio files
        if (function_exists('wc_get_product')) {
            $product = wc_get_product($postId);
            if ($product && method_exists($product, 'get_downloads')) {
                $downloads = $product->get_downloads();
                foreach ($downloads as $download) {
                    if (preg_match('/\.(mp3|ogg|wav|m4a)$/i', $download['file'])) {
                        return true;
                    }
                }
            }
        }
        
        // Check for audio in post content
        $content = get_post_field('post_content', $postId);
        if (strpos($content, '<audio') !== false || strpos($content, '[audio') !== false) {
            return true;
        }
        
        return false;
    }
}
