<?php
namespace bfa\UI;

use bfa\Plugin;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Handles admin column additions for Bandfront Analytics
 */
class Columns {
    
    private Plugin $plugin;
    
    public function __construct(Plugin $plugin) {
        $this->plugin = $plugin;
        $this->initHooks();
    }
    
    /**
     * Initialize column hooks
     */
    private function initHooks(): void {
        // Post/Page columns
        add_filter('manage_posts_columns', [$this, 'addViewsColumn']);
        add_action('manage_posts_custom_column', [$this, 'displayViewsColumn'], 10, 2);
        add_filter('manage_pages_columns', [$this, 'addViewsColumn']);
        add_action('manage_pages_custom_column', [$this, 'displayViewsColumn'], 10, 2);
        
        // Product columns (WooCommerce)
        add_action('init', [$this, 'setupProductColumns']);
        
        // User columns
        add_filter('manage_users_columns', [$this, 'addUserColumns']);
        add_filter('manage_users_custom_column', [$this, 'displayUserColumn'], 10, 3);
        add_filter('manage_users_sortable_columns', [$this, 'makeUserColumnsSortable']);
        add_action('pre_get_users', [$this, 'handleUserColumnSorting']);
        
        // Track user login
        add_action('wp_login', [$this, 'trackUserLogin'], 10, 2);
    }
    
    /**
     * Add views column to posts/pages list
     */
    public function addViewsColumn(array $columns): array {
        if (!$this->plugin->getConfig()->get('show_post_columns', true)) {
            return $columns;
        }
        
        $columns['bfa_views'] = '<span class="dashicons dashicons-visibility"></span>';
        return $columns;
    }
    
    /**
     * Display views count in column
     */
    public function displayViewsColumn(string $column, int $postId): void {
        if (!$this->plugin->getConfig()->get('show_post_columns', true)) {
            return;
        }
        
        if ($column === 'bfa_views') {
            $views = $this->plugin->getDatabase()->getPostViews($postId);
            echo '<strong>' . number_format($views) . '</strong>';
        }
    }
    
    /**
     * Setup product columns for WooCommerce
     */
    public function setupProductColumns(): void {
        if (!class_exists('WooCommerce')) {
            return;
        }
        
        // Add columns
        add_filter('manage_product_posts_columns', [$this, 'addProductColumns']);
        add_action('manage_product_posts_custom_column', [$this, 'displayProductColumn'], 10, 2);
        
        // Make columns sortable
        add_filter('manage_edit-product_sortable_columns', [$this, 'makeProductColumnsSortable']);
        add_action('pre_get_posts', [$this, 'handleProductColumnSorting']);
    }
    
    /**
     * Add custom columns to WooCommerce products
     */
    public function addProductColumns(array $columns): array {
        if (!$this->plugin->getConfig()->get('show_product_columns', true)) {
            return $columns;
        }
        
        // Enqueue admin styles
        wp_enqueue_style(
            'bfa-admin-columns', 
            BFA_PLUGIN_URL . 'assets/css/admin-columns.css', 
            [], 
            BFA_VERSION
        );
        
        // Insert our columns before the date column
        $new_columns = [];
        
        foreach ($columns as $key => $value) {
            // Add columns before the date column
            if ($key === 'date') {
                $new_columns['bfa_views'] = '<span class="dashicons dashicons-visibility" title="' . esc_attr__('Page Views', 'bandfront-analytics') . '"></span>';
                $new_columns['bfa_play_counter'] = '<span class="dashicons dashicons-format-audio" title="' . esc_attr__('Play Count', 'bandfront-analytics') . '"></span>';
            }
            $new_columns[$key] = $value;
        }
        
        // If date column wasn't found (shouldn't happen), add at the end
        if (!isset($new_columns['bfa_views'])) {
            $new_columns['bfa_views'] = '<span class="dashicons dashicons-visibility" title="' . esc_attr__('Page Views', 'bandfront-analytics') . '"></span>';
            $new_columns['bfa_play_counter'] = '<span class="dashicons dashicons-format-audio" title="' . esc_attr__('Play Count', 'bandfront-analytics') . '"></span>';
        }
        
        return $new_columns;
    }
    
    /**
     * Display content for product columns
     */
    public function displayProductColumn(string $column, int $productId): void {
        if (!$this->plugin->getConfig()->get('show_product_columns', true)) {
            return;
        }
        
        switch ($column) {
            case 'bfa_views':
                $views = $this->plugin->getDatabase()->getPostViews($productId);
                echo '<span class="bfa-view-counter">' . number_format($views) . '</span>';
                break;
                
            case 'bfa_play_counter':
                $plays = $this->getProductPlayCount($productId);
                echo '<span class="bfa-play-counter">' . number_format($plays) . '</span>';
                break;
        }
    }
    
    /**
     * Make product columns sortable
     */
    public function makeProductColumnsSortable(array $columns): array {
        $columns['bfa_views'] = 'bfa_views';
        $columns['bfa_play_counter'] = 'bfa_play_counter';
        return $columns;
    }
    
    /**
     * Handle sorting for custom product columns
     */
    public function handleProductColumnSorting(\WP_Query $query): void {
        if (!is_admin() || !$query->is_main_query()) {
            return;
        }
        
        $orderby = $query->get('orderby');
        
        if ($orderby === 'bfa_views') {
            $query->set('meta_key', '_bfa_total_views');
            $query->set('orderby', 'meta_value_num');
        } elseif ($orderby === 'bfa_play_counter') {
            $query->set('meta_key', '_bfa_play_counter');
            $query->set('orderby', 'meta_value_num');
        }
    }
    
    /**
     * Get play count for a product
     */
    private function getProductPlayCount(int $productId): int {
        // First check post meta cache
        $cached = get_post_meta($productId, '_bfa_play_counter', true);
        if ($cached !== '') {
            return (int) $cached;
        }
        
        // Query database for play events
        $database = $this->plugin->getDatabase();
        $plays = $database->getProductPlayCount($productId);
        
        // Cache the result
        update_post_meta($productId, '_bfa_play_counter', $plays);
        
        return $plays;
    }
    
    /**
     * Add custom columns to users list
     */
    public function addUserColumns(array $columns): array {
        if (!$this->plugin->getConfig()->get('show_user_columns', true)) {
            return $columns;
        }
        
        // Add after the email column
        $new_columns = [];
        foreach ($columns as $key => $value) {
            $new_columns[$key] = $value;
            if ($key === 'email') {
                $new_columns['bfa_last_login'] = __('Last Login', 'bandfront-analytics');
                $new_columns['bfa_login_count'] = '<span class="dashicons dashicons-chart-line" title="' . esc_attr__('Total Logins', 'bandfront-analytics') . '"></span>';
            }
        }
        
        // If email column wasn't found, add at the end
        if (!isset($new_columns['bfa_last_login'])) {
            $new_columns['bfa_last_login'] = __('Last Login', 'bandfront-analytics');
            $new_columns['bfa_login_count'] = '<span class="dashicons dashicons-chart-line" title="' . esc_attr__('Total Logins', 'bandfront-analytics') . '"></span>';
        }
        
        return $new_columns;
    }
    
    /**
     * Display content for user columns
     */
    public function displayUserColumn(string $value, string $column, int $userId): string {
        if (!$this->plugin->getConfig()->get('show_user_columns', true)) {
            return $value;
        }
        
        switch ($column) {
            case 'bfa_last_login':
                $lastLogin = get_user_meta($userId, '_bfa_last_login', true);
                if ($lastLogin) {
                    $timestamp = strtotime($lastLogin);
                    $time_diff = current_time('timestamp') - $timestamp;
                    
                    // Format based on how recent
                    if ($time_diff < DAY_IN_SECONDS) {
                        $value = sprintf(__('%s ago', 'bandfront-analytics'), human_time_diff($timestamp));
                    } else {
                        $value = date_i18n(get_option('date_format') . ' ' . get_option('time_format'), $timestamp);
                    }
                    
                    // Add visual indicator for recent logins
                    if ($time_diff < HOUR_IN_SECONDS) {
                        $value = '<span class="bfa-online-indicator" title="' . esc_attr__('Recently active', 'bandfront-analytics') . '">🟢</span> ' . $value;
                    }
                } else {
                    $value = '<span style="color: #999;">' . __('Never', 'bandfront-analytics') . '</span>';
                }
                break;
                
            case 'bfa_login_count':
                $count = (int) get_user_meta($userId, '_bfa_login_count', true);
                $value = '<strong>' . number_format($count) . '</strong>';
                break;
        }
        
        return $value;
    }
    
    /**
     * Make user columns sortable
     */
    public function makeUserColumnsSortable(array $columns): array {
        $columns['bfa_last_login'] = 'bfa_last_login';
        $columns['bfa_login_count'] = 'bfa_login_count';
        return $columns;
    }
    
    /**
     * Handle sorting for custom user columns
     */
    public function handleUserColumnSorting(\WP_User_Query $query): void {
        if (!is_admin()) {
            return;
        }
        
        $orderby = $query->get('orderby');
        
        if ($orderby === 'bfa_last_login') {
            $query->set('meta_key', '_bfa_last_login');
            $query->set('orderby', 'meta_value');
        } elseif ($orderby === 'bfa_login_count') {
            $query->set('meta_key', '_bfa_login_count');
            $query->set('orderby', 'meta_value_num');
        }
    }
    
    /**
     * Track user login
     */
    public function trackUserLogin(string $userLogin, \WP_User $user): void {
        // Update last login time
        update_user_meta($user->ID, '_bfa_last_login', current_time('mysql'));
        
        // Increment login count
        $currentCount = (int) get_user_meta($user->ID, '_bfa_login_count', true);
        update_user_meta($user->ID, '_bfa_login_count', $currentCount + 1);
        
        // Track in analytics events table
        $this->plugin->getDatabase()->recordEvent([
            'event_type' => 'user_login',
            'object_id' => $user->ID,
            'object_type' => 'user',
            'session_id' => md5($user->ID . time()),
            'meta_data' => [
                'user_role' => implode(',', $user->roles),
                'login_count' => $currentCount + 1,
            ],
        ]);
    }
}
