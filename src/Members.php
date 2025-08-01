<?php
namespace bfp;

/**
 * Player management functionality for Bandfront Player
 *
 * @package BandfrontPlayer
 * @since 0.1
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * Player Manager Class
 * Central hub for all player operations
 */
class Player {
    
    private Plugin $mainPlugin;
    private bool $enqueuedResources = false;
    private bool $insertedPlayer = false;
    private bool $insertPlayer = true;
    private bool $insertMainPlayer = true;
    private bool $insertAllPlayers = true;
    private int $preloadTimes = 0;
    
    public function __construct(Plugin $mainPlugin) {
        $this->mainPlugin = $mainPlugin;
    }
    
    /**
     * Generate player HTML
     */
    public function getPlayer(string $audioUrl, array $args = []): string {
        $productId = $args['product_id'] ?? 0;
        $playerControls = $args['player_controls'] ?? '';
        // Use state manager for default player style instead of constant
        $playerStyle = $args['player_style'] ?? $this->mainPlugin->getState('_bfp_player_layout');
        $mediaType = $args['media_type'] ?? 'mp3';
        $id = $args['id'] ?? '0';  // Allow string IDs
        $duration = $args['duration'] ?? false;
        $preload = $args['preload'] ?? 'none';
        $volume = $args['volume'] ?? 1;
        
        // Apply filters
        $preload = apply_filters('bfp_preload', $preload, $audioUrl);
        
        // Generate unique player ID
        $playerId = 'bfp-player-' . $productId . '-' . $id . '-' . uniqid();
        
        // Build player HTML
        $playerHtml = '<audio id="' . esc_attr($playerId) . '" ';
        $playerHtml .= 'class="bfp-player ' . esc_attr($playerStyle) . '" ';
        $playerHtml .= 'data-product-id="' . esc_attr($productId) . '" ';
        $playerHtml .= 'data-file-index="' . esc_attr($id) . '" ';
        $playerHtml .= 'preload="' . esc_attr($preload) . '" ';
        $playerHtml .= 'data-volume="' . esc_attr($volume) . '" ';
        
        if ($playerControls) {
            $playerHtml .= 'data-controls="' . esc_attr($playerControls) . '" ';
        }
        
        if ($duration) {
            $playerHtml .= 'data-duration="' . esc_attr($duration) . '" ';
        }
        
        $playerHtml .= '>';
        $playerHtml .= '<source src="' . esc_url($audioUrl) . '" type="audio/' . esc_attr($mediaType) . '" />';
        $playerHtml .= '</audio>';
        
        return apply_filters('bfp_player_html', $playerHtml, $audioUrl, $args);
    }
    
    /**
     * Include main player for a product
     */
    public function includeMainPlayer($product = '', bool $echo = true): string {
        $output = '';

        if (is_admin()) {
            return $output;
        }

        if (!$this->getInsertPlayer() || !$this->getInsertMainPlayer()) {
            return $output;
        }
        
        if (is_numeric($product)) {
            $product = wc_get_product($product);
        }
        if (!is_object($product)) {
            $product = wc_get_product();
        }

        if (empty($product)) {
            return '';
        }

        // Use getState for single value retrieval
        $onCover = $this->mainPlugin->getConfig()->getState('_bfp_on_cover');
        if ($onCover && (is_shop() || is_product_category() || is_product_tag())) {
            // Don't render the regular player on shop pages when on_cover is enabled
            // The play button will be handled by the hook manager
            return '';
        }

        $files = $this->getProductFilesInternal([
            'product' => $product,
            'first'   => true,
        ]);
        
        if (!empty($files)) {
            $id = $product->get_id();

            // Use getState for single value with product context
            $showIn = $this->mainPlugin->getConfig()->getState('_bfp_show_in', null, $id);
            if (
                ('single' == $showIn && (!function_exists('is_product') || !is_product())) ||
                ('multiple' == $showIn && (function_exists('is_product') && is_product()) && get_queried_object_id() == $id)
            ) {
                return $output;
            }
            
            // CONTEXT-AWARE CONTROLS: button on shop, full on product pages
            if (function_exists('is_product') && is_product()) {
                // Product page - always use full controls
                $playerControls = '';  // Empty string means 'all' controls
            } else {
                // Shop/archive pages - always use button only
                $playerControls = 'track';  // 'track' means button controls
            }
            
            // Get all player settings using bulk fetch for performance
            $settings = $this->mainPlugin->getConfig()->getStates([
                '_bfp_preload',
                '_bfp_player_layout',
                '_bfp_player_volume'
            ], $id);
            
            $this->enqueueResources();

            $file = reset($files);
            $index = key($files);  // This can be a string like "0_123"
            $duration = $this->mainPlugin->getAudioCore()->getDurationByUrl($file['file']);
            $audioUrl = $this->mainPlugin->getAudioCore()->generateAudioUrl($id, $index, $file);
            $audioTag = apply_filters(
                'bfp_audio_tag',
                $this->getPlayer(
                    $audioUrl,
                    [
                        'product_id'      => $id,
                        'player_controls' => $playerControls,  // Use context-aware controls
                        'player_style'    => $settings['_bfp_player_layout'],
                        'media_type'      => $file['media_type'],
                        'id'              => $index,  // Pass the file index
                        'duration'        => $duration,
                        'preload'         => $settings['_bfp_preload'],
                        'volume'          => $settings['_bfp_player_volume'],
                    ]
                ),
                $id,
                $index,
                $audioUrl
            );

            do_action('bfp_before_player_shop_page', $id);

            $output = '<div class="bfp-player-container product-' . esc_attr($file['product']) . '">' . $audioTag . '</div>';
            if ($echo) {
                print $output; // phpcs:ignore WordPress.Security.EscapeOutput
            }

            do_action('bfp_after_player_shop_page', $id);

            return $output; // phpcs:ignore WordPress.Security.EscapeOutput
        }
        
        return '';
    }
    
    /**
     * Include all players for a product
     * Moved from player-renderer.php
     */
    public function includeAllPlayers($product = ''): void {
        if (!$this->getInsertPlayer() || !$this->getInsertAllPlayers() || is_admin()) {
            return;
        }

        if (!is_object($product)) {
            $product = wc_get_product();
        }

        if (empty($product)) {
            return;
        }

        $files = $this->getProductFilesInternal([
            'product' => $product,
            'all'     => true,
        ]);
        
        if (!empty($files)) {
            $id = $product->get_id();

            // Use getState for single value with product context
            $showIn = $this->mainPlugin->getConfig()->getState('_bfp_show_in', null, $id);
            if (
                ('single' == $showIn && !is_singular()) ||
                ('multiple' == $showIn && is_singular())
            ) {
                return;
            }
            
            // Get all player settings using bulk fetch
            $settings = $this->mainPlugin->getConfig()->getStates([
                '_bfp_preload',
                '_bfp_player_layout',
                '_bfp_player_volume',
                '_bfp_player_title',
                '_bfp_loop',
                '_bfp_merge_in_grouped'
            ], $id);
            
            $this->enqueueResources();
            
            // CONTEXT-AWARE CONTROLS: Always use full controls on product pages
            if (function_exists('is_product') && is_product()) {
                // Product page - always use full controls ('all')
                $playerControls = 'all';
            } else {
                // Shop/archive pages - use button only
                $playerControls = 'button';
            }
            
            $mergeGroupedClass = ($settings['_bfp_merge_in_grouped']) ? 'merge_in_grouped_products' : '';

            $counter = count($files);

            do_action('bfp_before_players_product_page', $id);
            
            if (1 == $counter) {
                $playerControls = ('button' == $playerControls) ? 'track' : '';
                $file = reset($files);
                $index = key($files);
                $duration = $this->mainPlugin->getAudioCore()->getDurationByUrl($file['file']);
                $audioUrl = $this->mainPlugin->getAudioCore()->generateAudioUrl($id, $index, $file);
                $audioTag = apply_filters(
                    'bfp_audio_tag',
                    $this->getPlayer(
                        $audioUrl,
                        [
                            'product_id'      => $id,
                            'player_controls' => $playerControls,
                            'player_style'    => $settings['_bfp_player_layout'],
                            'media_type'      => $file['media_type'],
                            'duration'        => $duration,
                            'preload'         => $settings['_bfp_preload'],
                            'volume'          => $settings['_bfp_player_volume'],
                        ]
                    ),
                    $id,
                    $index,
                    $audioUrl
                );
                $title = esc_html(($settings['_bfp_player_title']) ? apply_filters('bfp_file_name', $file['name'], $id, $index) : '');
                print '<div class="bfp-player-container ' . esc_attr($mergeGroupedClass) . ' product-' . esc_attr($file['product']) . '" ' . ($settings['_bfp_loop'] ? 'data-loop="1"' : '') . '>' . $audioTag . '</div><div class="bfp-player-title" data-audio-url="' . esc_attr($audioUrl) . '">' . wp_kses_post($title) . '</div><div style="clear:both;"></div>'; // phpcs:ignore WordPress.Security.EscapeOutput
            } elseif ($counter > 1) {
                // Use the table renderer for multiple files
                $singlePlayer = intval($this->mainPlugin->getConfig()->getState('_bfp_single_player', 0, $id));
                
                // Add player_controls to settings for renderer
                $settings['player_controls'] = $playerControls;
                $settings['single_player'] = $singlePlayer;
                
                print $this->renderPlayerTable($files, $id, $settings); // phpcs:ignore WordPress.Security.EscapeOutput
            }
            
            // Fix: Check if WooCommerce integration exists
            $purchased = false;
            $woocommerce = $this->mainPlugin->getWooCommerce();
            if ($woocommerce) {
                $purchased = $woocommerce->woocommerceUserProduct($id);
            }
            
            $message = $this->mainPlugin->getConfig()->getState('_bfp_message');
            if (!empty($message) && false === $purchased) {
                print '<div class="bfp-message">' . wp_kses_post(__($message, 'bandfront-player')) . '</div>'; // phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralText
            }
            do_action('bfp_after_players_product_page', $id);
        }
    }
    
    /**
     * Render player table layout for multiple files
     * Moved from player-renderer.php
     */
    private function renderPlayerTable(array $files, int $productId, array $settings): string {
        if (empty($files) || count($files) < 2) {
            return '';
        }
        
        $output = '';
        $mergeGroupedClass = ($settings['_bfp_merge_in_grouped']) ? 'merge_in_grouped_products' : '';
        $singlePlayer = $settings['single_player'] ?? 0;
        
        $output .= '<table class="bfp-player-list ' . $mergeGroupedClass . ($singlePlayer ? ' bfp-single-player ' : '') . '" ' . 
                   ($settings['_bfp_loop'] ? 'data-loop="1"' : '') . '>';
        
        $counter = count($files);
        $firstPlayerClass = 'bfp-first-player';
        
        foreach ($files as $index => $file) {
            $evenOdd = (1 == $counter % 2) ? 'bfp-odd-row' : 'bfp-even-row';
            $counter--;
            
            $audioUrl = $this->mainPlugin->getAudioCore()->generateAudioUrl($productId, $index, $file);
            $duration = $this->mainPlugin->getAudioCore()->getDurationByUrl($file['file']);
            
            $audioTag = apply_filters(
                'bfp_audio_tag',
                $this->getPlayer(
                    $audioUrl,
                    [
                        'product_id'      => $productId,
                        'player_style'    => $settings['_bfp_player_layout'],
                        'player_controls' => ('all' != $settings['player_controls']) ? 'track' : '',
                        'media_type'      => $file['media_type'],
                        'duration'        => $duration,
                        'preload'         => $settings['_bfp_preload'],
                        'volume'          => $settings['_bfp_player_volume'],
                    ]
                ),
                $productId,
                $index,
                $audioUrl
            );
            
            $title = esc_html(($settings['_bfp_player_title']) ? 
                     apply_filters('bfp_file_name', $file['name'], $productId, $index) : '');
            
            $output .= $this->renderPlayerRow($audioTag, $title, $duration, $evenOdd, 
                                            $file['product'], $firstPlayerClass, 
                                            $counter, $settings, $singlePlayer);
            
            $firstPlayerClass = '';
        }
        
        $output .= '</table>';
        
        return $output;
    }
    
    /**
     * Render a single player row
     * Moved from player-renderer.php
     */
    private function renderPlayerRow(string $audioTag, string $title, string $duration, string $evenOdd, 
                                   int $productId, string $firstPlayerClass, int $counter, 
                                   array $settings, int $singlePlayer): string {
        $output = '<tr class="' . esc_attr($evenOdd) . ' product-' . esc_attr($productId) . '">';
        
        if ('all' != $settings['player_controls']) {
            $output .= '<td class="bfp-column-player-' . esc_attr($settings['_bfp_player_layout']) . '">';
            $output .= '<div class="bfp-player-container ' . $firstPlayerClass . '" data-player-id="' . esc_attr($counter) . '">';
            $output .= $audioTag;
            $output .= '</div></td>';
            $output .= '<td class="bfp-player-title bfp-column-player-title" data-player-id="' . esc_attr($counter) . '">';
            $output .= wp_kses_post($title);
            $output .= '</td>';
            $output .= '<td class="bfp-file-duration" style="text-align:right;font-size:16px;">';
            $output .= esc_html($duration);
            $output .= '</td>';
        } else {
            $output .= '<td>';
            $output .= '<div class="bfp-player-container ' . $firstPlayerClass . '" data-player-id="' . esc_attr($counter) . '">';
            $output .= $audioTag;
            $output .= '</div>';
            $output .= '<div class="bfp-player-title bfp-column-player-title" data-player-id="' . esc_attr($counter) . '">';
            $output .= wp_kses_post($title);
            if ($singlePlayer) {
                $output .= '<span class="bfp-file-duration">' . esc_html($duration) . '</span>';
            }
            $output .= '</div>';
            $output .= '</td>';
        }
        
        $output .= '</tr>';
        
        return $output;
    }
    
    /**
     * Get product files - internal use
     * Moved from player-renderer.php
     */
    private function getProductFilesInternal(array $args): array {
        if (empty($args['product'])) {
            return [];
        }

        $product = $args['product'];
        $files = $this->getAllProducts($product, []);
        if (empty($files)) {
            return [];
        }

        $audioFiles = [];
        foreach ($files as $index => $file) {
            if (!empty($file['file']) && false !== ($mediaType = $this->mainPlugin->getAudioCore()->isAudio($file['file']))) {
                $file['media_type'] = $mediaType;

                if (isset($args['file_id'])) {
                    if ($args['file_id'] == $index) {
                        $audioFiles[$index] = $file;
                        return $audioFiles;
                    }
                } elseif (!empty($args['first'])) {
                    $audioFiles[$index] = $file;
                    return $audioFiles;
                } elseif (!empty($args['all'])) {
                    $audioFiles[$index] = $file;
                }
            }
        }
        return $audioFiles;
    }
    
    /**
     * Get recursive product files
     * Moved from player-renderer.php
     */
    private function getAllProducts($product, array $filesArr): array {
        if (!is_object($product) || !method_exists($product, 'get_type')) {
            return $filesArr;
        }

        $productType = $product->get_type();
        $id = $product->get_id();
        
        // Fix: Check if WooCommerce integration exists before calling its methods
        $purchased = false;
        $woocommerce = $this->mainPlugin->getWooCommerce();
        if ($woocommerce) {
            $purchased = $woocommerce->woocommerceUserProduct($id);
        }

        if ('variation' == $productType) {
            $_files = $product->get_downloads();
            $_files = $this->editFilesArray($id, $_files);
            $filesArr = array_merge($filesArr, $_files);
        } else {
            if (!$this->mainPlugin->getConfig()->getState('_bfp_enable_player', false, $id)) {
                return $filesArr;
            }

            $ownDemos = intval($this->mainPlugin->getConfig()->getState('_bfp_own_demos', 0, $id));
            $files = $this->mainPlugin->getConfig()->getState('_bfp_demos_list', [], $id);
            if (false === $purchased && $ownDemos && !empty($files)) {
                $directOwnDemos = intval($this->mainPlugin->getConfig()->getState('_bfp_direct_own_demos', 0, $id));
                $files = $this->editFilesArray($id, $files, $directOwnDemos);
                $filesArr = array_merge($filesArr, $files);
            } else {
                switch ($productType) {
                    case 'variable':
                    case 'grouped':
                        $children = $product->get_children();

                        foreach ($children as $key => $childId) {
                            $children[$key] = wc_get_product($childId);
                        }

                        uasort($children, [Utils\Utils::class, 'sortList']);

                        foreach ($children as $childObj) {
                            $filesArr = $this->getAllProducts($childObj, $filesArr);
                        }
                        break;
                    default:
                        $_files = $product->get_downloads();
                        if (empty($_files) && $ownDemos && !empty($files)) {
                            $_files = $this->editFilesArray($id, $files);
                        } else {
                            $_files = $this->editFilesArray($id, $_files);
                        }
                        $filesArr = array_merge($filesArr, $_files);
                        break;
                }
            }
        }
        return $filesArr;
    }
    
    /**
     * Edit files array helper method
     * Moved from player-renderer.php
     */
    private function editFilesArray(int $productId, array $files, int $playSrc = 0): array {
        $pFiles = [];
        foreach ($files as $key => $file) {
            $pKey = $key . '_' . $productId;
            if (gettype($file) == 'object') {
                $file = (array) $file->get_data();
            }
            $file['product'] = $productId;
            $file['play_src'] = $playSrc;
            $pFiles[$pKey] = $file;
        }
        return $pFiles;
    }
    
    /**
     * Get product files - public interface
     * This is the method that other classes should use
     */
    public function getProductFiles($productId): array {
        $product = wc_get_product($productId);
        if (!$product) {
            return [];
        }
        
        return $this->getProductFilesInternal([
            'product' => $product,
            'all' => true
        ]);
    }
    
    /**
     * Get enqueued resources state
     */
    public function getEnqueuedResources(): bool {
        return $this->enqueuedResources;
    }
    
    /**
     * Set enqueued resources state
     */
    public function setEnqueuedResources(bool $value): void {
        $this->enqueuedResources = $value;
    }
    
    /**
     * Get insert player flag
     */
    public function getInsertPlayer(): bool {
        return $this->insertPlayer;
    }
    
    /**
     * Set insert player flag
     */
    public function setInsertPlayer(bool $value): void {
        $this->insertPlayer = $value;
    }
    
    /**
     * Get inserted player state
     */
    public function getInsertedPlayer(): bool {
        return $this->insertedPlayer;
    }
    
    /**
     * Set inserted player state
     */
    public function setInsertedPlayer(bool $value): void {
        $this->insertedPlayer = $value;
    }
    
    /**
     * Get insert main player flag
     */
    public function getInsertMainPlayer(): bool {
        return $this->insertMainPlayer;
    }
    
    /**
     * Set insert main player flag
     */
    public function setInsertMainPlayer(bool $value): void {
        $this->insertMainPlayer = $value;
    }
    
    /**
     * Get insert all players flag
     */
    public function getInsertAllPlayers(): bool {
        return $this->insertAllPlayers;
    }
    
    /**
     * Set insert all players flag
     */
    public function setInsertAllPlayers(bool $value): void {
        $this->insertAllPlayers = $value;
    }
    
    /**
     * Check if current device is iOS
     */
    private function isIosDevice(): bool {
        static $isIos = null;
        
        if ($isIos === null) {
            $isIos = isset($_SERVER['HTTP_USER_AGENT']) && 
                     preg_match('/(iPad|iPhone|iPod)/i', $_SERVER['HTTP_USER_AGENT']);
        }
        
        return $isIos;
    }
    
    /**
     * Enqueue player resources
     */
    public function enqueueResources(): void {
        if ($this->enqueuedResources) {
            return;
        }
        
        global $BandfrontPlayer;
        
        // Use getState for single value retrieval
        $audioEngine = $BandfrontPlayer->getConfig()->getState('_bfp_audio_engine');
        
        // Enqueue base styles
        wp_enqueue_style(
            'bfp-style', 
            plugin_dir_url(dirname(__FILE__)) . 'css/style.css', 
            [], 
            BFP_VERSION
        );
        
        // Enqueue jQuery
        wp_enqueue_script('jquery');
        
        if ($audioEngine === 'wavesurfer') {
            // Check if WaveSurfer is available locally
            $wavesurferPath = plugin_dir_path(dirname(__FILE__)) . 'vendor/wavesurfer/wavesurfer.min.js';
            
            if (file_exists($wavesurferPath)) {
                // Enqueue local WaveSurfer.js
                wp_enqueue_script(
                    'wavesurfer',
                    plugin_dir_url(dirname(__FILE__)) . 'vendor/wavesurfer/wavesurfer.min.js',
                    [],
                    '7.9.9',
                    true
                );
            } else {
                // Fallback to CDN if local file doesn't exist
                wp_enqueue_script(
                    'wavesurfer',
                    'https://unpkg.com/wavesurfer.js@7/dist/wavesurfer.min.js',
                    [],
                    '7.9.9',
                    true
                );
            }
            
            // Enqueue WaveSurfer integration (our custom file)
            wp_enqueue_script(
                'bfp-wavesurfer-integration',
                plugin_dir_url(dirname(__FILE__)) . 'js/wavesurfer.js',
                ['jquery', 'wavesurfer'],
                BFP_VERSION,
                true
            );
        } else {
            // Enqueue MediaElement.js
            wp_enqueue_style('wp-mediaelement');
            wp_enqueue_script('wp-mediaelement');
            
            // Use getState for single value retrieval
            $selectedSkin = $BandfrontPlayer->getConfig()->getState('_bfp_player_layout');
            
            // Map old skin names to new ones if needed
            $skinMapping = [
                'mejs-dark' => 'dark',
                'mejs-light' => 'light',
                'custom' => 'custom'
            ];
            
            if (isset($skinMapping[$selectedSkin])) {
                $selectedSkin = $skinMapping[$selectedSkin];
            }
            
            // Validate skin selection
            if (!in_array($selectedSkin, ['dark', 'light', 'custom'])) {
                $selectedSkin = 'dark';
            }
            
            // Enqueue selected skin CSS file
            wp_enqueue_style(
                'bfp-skin-' . $selectedSkin,
                plugin_dir_url(dirname(__FILE__)) . 'css/skins/' . $selectedSkin . '.css',
                ['wp-mediaelement'],
                BFP_VERSION
            );
        }
        
        // Enqueue main engine script
        wp_enqueue_script(
            'bfp-engine',
            plugin_dir_url(dirname(__FILE__)) . 'js/engine.js',
            ['jquery'],
            BFP_VERSION,
            true
        );
        
        // Localize script with settings using bulk fetch
        $settingsKeys = [
            '_bfp_play_simultaneously',
            '_bfp_ios_controls',
            '_bfp_fade_out',
            '_bfp_on_cover',
            '_bfp_enable_visualizations',
            '_bfp_player_layout'
        ];
        
        $settings = $BandfrontPlayer->getConfig()->getStates($settingsKeys);
        
        $jsSettings = [
            'ajaxurl' => admin_url('admin-ajax.php'),
            'audio_engine' => $audioEngine,
            'play_simultaneously' => $settings['_bfp_play_simultaneously'],
            'ios_controls' => $settings['_bfp_ios_controls'],
            'fade_out' => $settings['_bfp_fade_out'],
            'on_cover' => $settings['_bfp_on_cover'],
            'visualizations' => $settings['_bfp_enable_visualizations'],
            'player_skin' => $settings['_bfp_player_layout']
        ];
        
        // Get smart settings with auto-detection
        $smartSettings = [
            'ios_controls' => $this->isIosDevice() ? 1 : 0,
            'onload' => 1, // Always use onload for better compatibility
        ];
        
        // Merge with user settings
        $settings = array_merge(
            $smartSettings,
            $this->mainPlugin->getConfig()->getStates([
                '_bfp_force_main_player_in_title'
            ])
        );
        
        wp_localize_script('bfp-engine', 'bfp_global_settings', $settings);
        
        $this->enqueuedResources = true;
    }
}