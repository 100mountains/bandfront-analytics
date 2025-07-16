<?php
namespace bfa;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Config Class for Bandfront Analytics
 */
class Config {
    
    private array $settings = [];
    private string $optionName = 'bfa_settings';
    private array $defaults = [];
    
    public function __construct() {
        $this->initializeDefaults();
        $this->loadSettings();
    }
    
    /**
     * Initialize default settings
     */
    private function initializeDefaults(): void {
        $this->defaults = [
            // Core tracking settings
            'tracking_enabled' => true,
            'track_logged_in_users' => true,
            'exclude_admins' => true,
            'anonymize_ip' => true,
            'respect_dnt' => true,
            
            // Performance settings
            'sampling_rate' => 0.1, // 10% sampling when over threshold
            'sampling_threshold' => 10000, // Daily views before sampling kicks in
            'batch_size' => 100, // Events per batch write
            'realtime_buffer_size' => 50, // Events before flush
            'realtime_timeout' => 5, // Seconds before force flush
            
            // Data retention
            'retention_days' => 365, // Keep raw data for 1 year
            'archive_after_days' => 30, // Archive after 30 days
            'aggregate_interval' => 3600, // Hourly aggregation
            
            // Cache settings
            'cache_ttl' => 300, // 5 minutes cache
            'use_object_cache' => true,
            
            // Admin Display settings
            'show_admin_bar_widget' => true,
            'show_dashboard_widget' => true,
            'show_post_columns' => true,
            'show_product_columns' => true,
            'show_user_columns' => true,
            'default_date_range' => 'last_7_days',
            
            // Chart settings
            'chart_colors' => [
                'primary' => '#0073aa',
                'secondary' => '#23282d',
                'accent' => '#00a0d2',
            ],
            
            // Music tracking
            'track_music_plays' => true,
            'track_play_duration' => true,
            'track_completion_rate' => true,
            
            // API Settings
            'enable_api' => true,
            'api_rate_limit' => 100,
            'api_authentication' => false,
            
            // Membership Tier Settings
            'membership_tiers' => [
                'basic' => [
                    'name' => __('Basic Member', 'bandfront-members'),
                    'price' => 0,
                    'duration' => 'lifetime',
                    'benefits' => [],
                ],
                'premium' => [
                    'name' => __('Premium Member', 'bandfront-members'),
                    'price' => 9.99,
                    'duration' => 'monthly',
                    'benefits' => [],
                ],
            ],
            'allow_tier_upgrades' => true,
            'prorate_upgrades' => true,
            
            // Email & Notification Settings
            'send_welcome_email' => true,
            'welcome_email_subject' => __('Welcome to {site_name}!', 'bandfront-members'),
            'welcome_email_template' => '',
            'send_expiration_reminders' => true,
            'expiration_reminder_days' => [7, 3, 1],
            'send_new_content_notifications' => true,
            'notification_frequency' => 'instant',
            
            'notify_admin_new_member' => true,
            'notify_admin_cancellation' => true,
            'admin_notification_email' => get_option('admin_email'),
            
            // Member Portal Settings
            'enable_member_dashboard' => true,
            'dashboard_page_id' => '',
            'show_membership_status' => true,
            'show_billing_history' => true,
            'allow_self_cancellation' => true,
            'show_exclusive_content_list' => true,
            'enable_member_directory' => false,
            'member_profile_fields' => ['bio', 'social_links'],
            
            // Analytics & Reporting Settings
            'enable_analytics' => true,
            'track_content_views' => true,
            'track_member_logins' => true,
            'track_conversion_events' => true,
            'analytics_retention_days' => 90,
            'enable_revenue_reporting' => true,
            'enable_engagement_metrics' => true,
            
            // Security & Access Control Settings
            'require_email_verification' => true,
            'enable_two_factor_auth' => false,
            'session_timeout_minutes' => 0,
            'max_login_attempts' => 5,
            'lockout_duration_minutes' => 30,
            'enable_ip_restrictions' => false,
            'allowed_ip_addresses' => [],
            
            // Display & Customization Settings
            'primary_color' => '#0073aa',
            'secondary_color' => '#23282d',
            'button_style' => 'rounded',
            'custom_css' => '',
            'login_logo_url' => '',
            'member_badge_text' => __('Member', 'bandfront-members'),
            'show_member_since_date' => true,
            
            // Integration Settings
            'mailchimp_enabled' => false,
            'mailchimp_api_key' => '',
            'mailchimp_list_id' => '',
            'discord_integration_enabled' => false,
            'discord_webhook_url' => '',
            'zapier_enabled' => false,
            'zapier_webhook_url' => '',
            
            // Advanced Settings
            'cache_enabled' => true,
            'cache_duration_minutes' => 60,
            'enable_debug_mode' => false,
            'log_level' => 'error',
            'database_prefix' => 'bfm_',
            
            // Invitation System Settings
            'enable_invitations' => false,
            'invitation_code_length' => 8,
            'invitation_expiry_days' => 30,
            'max_invites_per_member' => 5,
            'reward_referrals' => false,
            'referral_bonus_days' => 30,
            
            // Legacy compatibility mappings (remove in future versions)
            '_bfp_backer_role' => 'subscriber', // Maps to backer_role
            '_bfp_posts_page' => '', // Maps to posts_page
        ];
    }
    
    /**
     * Load settings from WordPress options
     */
    private function loadSettings(): void {
        $saved = get_option($this->optionName, []);
        $this->settings = wp_parse_args($saved, $this->defaults);
    }
    
    /**
     * Save settings to WordPress options
     */
    public function save(array $settings): bool {
        $this->settings = wp_parse_args($settings, $this->settings);
        return update_option($this->optionName, $this->settings);
    }
    
    /**
     * Get a setting value
     */
    public function get(string $key, $default = null) {
        // Handle legacy keys
        if (strpos($key, '_bfp_') === 0) {
            $key = $this->mapLegacyKey($key);
        }
        
        return $this->settings[$key] ?? $default ?? $this->defaults[$key] ?? null;
    }
    
    /**
     * Get multiple settings at once
     */
    public function getMultiple(array $keys): array {
        $result = [];
        foreach ($keys as $key) {
            $result[$key] = $this->get($key);
        }
        return $result;
    }
    
    /**
     * Set a setting value
     */
    public function set(string $key, $value): void {
        $this->settings[$key] = $value;
    }
    
    /**
     * Get all settings
     */
    public function getAll(): array {
        return $this->settings;
    }
    
    /**
     * Reset to defaults
     */
    public function resetToDefaults(): bool {
        $this->settings = $this->defaults;
        return update_option($this->optionName, $this->settings);
    }
    
    /**
     * Map legacy keys to new names
     */
    private function mapLegacyKey(string $oldKey): string {
        $mappings = [
            '_bfp_backer_role' => 'backer_role',
            '_bfp_posts_page' => 'posts_page',
            '_bfp_registered_only' => 'content_protection_enabled',
            '_bfp_message' => 'restricted_content_message',
        ];
        
        return $mappings[$oldKey] ?? str_replace('_bfp_', '', $oldKey);
    }
    
    /**
     * Check if a setting exists
     */
    public function has(string $key): bool {
        return isset($this->settings[$key]) || isset($this->defaults[$key]);
    }
    
    /**
     * Get settings for a specific section
     */
    public function getSection(string $section): array {
        $sections = [
            'membership' => ['membership_enabled', 'backer_role', 'posts_page', 'join_page', 'default_membership_tier'],
            'content' => ['content_protection_enabled', 'restricted_content_message', 'teaser_content_length', 'protect_post_types'],
            'payment' => ['stripe_enabled', 'paypal_enabled', 'woocommerce_enabled', 'manual_payments_enabled', 'payment_currency'],
            'email' => ['send_welcome_email', 'welcome_email_subject', 'send_expiration_reminders', 'notification_frequency'],
            'security' => ['require_email_verification', 'enable_two_factor_auth', 'max_login_attempts'],
            'display' => ['primary_color', 'secondary_color', 'button_style', 'member_badge_text'],
        ];
        
        $sectionKeys = $sections[$section] ?? [];
        return $this->getMultiple($sectionKeys);
    }
    
    /**
     * Legacy compatibility methods
     */
    public function getState(string $key, $default = null) {
        return $this->get($key, $default);
    }
    
    public function getStates(array $keys): array {
        return $this->getMultiple($keys);
    }
    
    public function getAdminFormSettings(): array {
        return $this->getAll();
    }
    
    public function updateGlobalAttrs(array $attrs): void {
        $this->save($attrs);
    }
    
    /**
     * Check if bot based on user agent
     */
    public function isBot(string $userAgent): bool {
        $bots = [
            'googlebot', 'bingbot', 'slurp', 'duckduckbot', 'baiduspider',
            'yandexbot', 'facebookexternalhit', 'twitterbot', 'linkedinbot',
            'whatsapp', 'applebot', 'pingdom', 'uptimerobot'
        ];
        
        $userAgentLower = strtolower($userAgent);
        foreach ($bots as $bot) {
            if (strpos($userAgentLower, $bot) !== false) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Check if sampling should be applied
     */
    public function shouldSample(): bool {
        // Only sample if over threshold
        $dailyViews = $GLOBALS['BandfrontAnalytics']->getDatabase()->getTodayPageviews();
        if ($dailyViews < $this->get('sampling_threshold', 10000)) {
            return false;
        }
        
        // Apply sampling rate
        $rate = $this->get('sampling_rate', 0.1);
        return (mt_rand(1, 100) / 100) > $rate;
    }
    
    /**
     * Debug method to get all current settings
     */
    public function debug(): array {
        return [
            'saved_settings' => $this->settings,
            'defaults' => $this->defaults,
            'merged' => array_merge($this->defaults, $this->settings),
        ];
    }
}
