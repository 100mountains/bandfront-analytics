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
        
        // Server-side tracking
        add_action('wp', [$this, 'trackServerSidePageview']);
        
        // Client-side tracking setup
        add_action('wp_enqueue_scripts', [$this, 'enqueueScripts']);
        add_action('wp_footer', [$this, 'outputTrackingData']);
    }
    
    /**
     * Track pageview server-side (for non-JS users)
     */
    public function trackServerSidePageview(): void {
        if (!is_singular() || is_admin()) {
            return;
        }
        
        // Let JavaScript handle it if enabled
        if ($this->plugin->getConfig()->get('use_javascript_tracking', true)) {
            return;
        }
        
        do_action('bfa_track_pageview', get_the_ID());
    }
    
    /**
     * Enqueue tracking scripts
     */
    public function enqueueScripts(): void {
        if (!$this->shouldTrack()) {
            return;
        }
        
        wp_enqueue_script(
            'bfa-tracker',
            plugin_dir_url($this->plugin->getFile()) . 'assets/js/tracker.js',
            [],
            $this->plugin->getVersion(),
            true
        );
        
        // Prepare tracking configuration
        $config = $this->plugin->getConfig();
        
        wp_localize_script('bfa-tracker', 'bfaTracker', [
            'apiUrl' => rest_url('bandfront-analytics/v1/'),
            'nonce' => wp_create_nonce('wp_rest'),
            'trackingEnabled' => true,
            'sampling' => $config->shouldSample() ? $config->get('sampling_rate', 1) : 1,
        ]);
    }
    
    /**
     * Output page-specific tracking data
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
        
        return true;
    }
    
    /**
     * Get the current page type
     */
    private function getPageType(): string {
        if (is_front_page()) return 'home';
        if (is_singular('product')) return 'product';
        if (is_singular('post')) return 'post';
        if (is_singular('page')) return 'page';
        if (is_archive()) return 'archive';
        if (is_search()) return 'search';
        if (is_404()) return '404';
        
        return 'other';
    }
    
    /**
     * Check if a product has audio files
     */
    private function hasAudioFiles(int $productId): bool {
        // Check for WooCommerce product audio files
        $audioFormats = ['mp3', 'wav', 'ogg', 'm4a'];
        
        // Check product meta for audio files
        $hasAudio = false;
        
        // Check if product has downloadable files with audio extensions
        if (function_exists('wc_get_product')) {
            $product = wc_get_product($productId);
            if ($product && $product->is_downloadable()) {
                $downloads = $product->get_downloads();
                foreach ($downloads as $download) {
                    $ext = pathinfo($download->get_file(), PATHINFO_EXTENSION);
                    if (in_array(strtolower($ext), $audioFormats)) {
                        $hasAudio = true;
                        break;
                    }
                }
            }
        }
        
        return $hasAudio;
    }
}
