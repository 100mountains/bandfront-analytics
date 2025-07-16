# Bandfront Analytics - External Plugin Integration Manual

## Overview

Bandfront Analytics provides a powerful hook-based system for other WordPress plugins to track events and retrieve analytics data. This manual covers how to integrate your plugin with Bandfront Analytics.

## Table of Contents

1. [Quick Start](#quick-start)
2. [Tracking Events](#tracking-events)
3. [Retrieving Analytics Data](#retrieving-analytics-data)
4. [Bandfront Player Integration](#bandfront-player-integration)
5. [WooCommerce Integration](#woocommerce-integration)
6. [Bandfront Members Integration](#bandfront-members-integration)
7. [Advanced Integration](#advanced-integration)
8. [Best Practices](#best-practices)
9. [API Reference](#api-reference)
10. [Testing and Development](#testing-and-development)

## Quick Start

### Check if Analytics is Active

```php
if (function_exists('do_action') && has_action('bfa_track')) {
    // Bandfront Analytics is available
}
```

### Track a Simple Event

```php
do_action('bfa_track', 'button_click', [
    'object_id' => 123,
    'value' => 1,
    'meta' => [
        'button_name' => 'play_button',
        'context' => 'homepage'
    ]
]);
```

## Tracking Events

### Basic Event Tracking

The main tracking action is `bfa_track`:

```php
do_action('bfa_track', $event_type, $data);
```

**Parameters:**
- `$event_type` (string): The type of event (e.g., 'pageview', 'click', 'play')
- `$data` (array): Event data with the following structure:
  - `object_id` (int): ID of the object being tracked (post ID, product ID, etc.)
  - `object_type` (string): Type of object (optional, auto-detected if not provided)
  - `value` (float): Numeric value associated with the event (optional)
  - `meta` (array): Additional metadata (optional)

### Specialized Tracking Actions

#### Track Pageview
```php
do_action('bfa_track_pageview', $post_id);
```

#### Track Music Play
```php
do_action('bfa_track_music_play', $track_id, $product_id);
```

#### Track Music Completion
```php
do_action('bfa_track_music_complete', $track_id, $product_id);
```

#### Track Download
```php
do_action('bfa_track_download', $file_id, $product_id);
```

## Retrieving Analytics Data

### Get Play Count
```php
$play_count = apply_filters('bfa_get_play_count', 0, $product_id);
```

### Get Post Views
```php
$view_count = apply_filters('bfa_get_post_views', 0, $post_id);
```

### Get Trending Items
```php
$trending = apply_filters('bfa_get_trending', [], [
    'days' => 7,      // Last 7 days
    'limit' => 10,    // Top 10 items
    'type' => 'all'   // 'all', 'product', 'post', etc.
]);
```

### Get Quick Stats
```php
$stats = apply_filters('bfa_get_quick_stats', []);
// Returns: [
//     'today_views' => 1234,
//     'today_plays' => 567,
//     'active_users' => 89,
//     'trending_count' => 10
// ]
```

## Bandfront Player Integration

### Complete Integration Example

```php
class BandfrontPlayer {
    
    public function __construct() {
        // Hook into player events
        add_action('bandfront_player_play', [$this, 'trackPlay'], 10, 2);
        add_action('bandfront_player_complete', [$this, 'trackComplete'], 10, 2);
        add_action('bandfront_player_download', [$this, 'trackDownload'], 10, 2);
        
        // Display play counts
        add_filter('bandfront_player_stats', [$this, 'addPlayCount'], 10, 2);
    }
    
    /**
     * Track when a track starts playing
     */
    public function trackPlay($track_id, $context) {
        if (!has_action('bfa_track_music_play')) {
            return;
        }
        
        $product_id = $this->getProductIdFromTrack($track_id);
        
        do_action('bfa_track_music_play', $track_id, $product_id);
        
        // Track additional context
        do_action('bfa_track', 'music_play_context', [
            'object_id' => $product_id,
            'meta' => [
                'track_id' => $track_id,
                'player_position' => $context['position'] ?? 'unknown',
                'autoplay' => $context['autoplay'] ?? false,
                'playlist_id' => $context['playlist_id'] ?? null,
            ]
        ]);
    }
    
    /**
     * Track when a track completes playing
     */
    public function trackComplete($track_id, $play_duration) {
        if (!has_action('bfa_track_music_complete')) {
            return;
        }
        
        $product_id = $this->getProductIdFromTrack($track_id);
        
        do_action('bfa_track_music_complete', $track_id, $product_id);
        
        // Track play duration
        do_action('bfa_track', 'music_duration', [
            'object_id' => $product_id,
            'value' => $play_duration,
            'meta' => [
                'track_id' => $track_id,
                'completion_percent' => 100,
            ]
        ]);
    }
    
    /**
     * Track downloads
     */
    public function trackDownload($file_id, $track_id) {
        if (!has_action('bfa_track_download')) {
            return;
        }
        
        $product_id = $this->getProductIdFromTrack($track_id);
        
        do_action('bfa_track_download', $file_id, $product_id);
    }
    
    /**
     * Add play count to player stats
     */
    public function addPlayCount($stats, $track_id) {
        if (!has_filter('bfa_get_play_count')) {
            return $stats;
        }
        
        $product_id = $this->getProductIdFromTrack($track_id);
        $play_count = apply_filters('bfa_get_play_count', 0, $product_id);
        
        $stats['plays'] = $play_count;
        $stats['plays_formatted'] = number_format($play_count);
        
        return $stats;
    }
    
    private function getProductIdFromTrack($track_id) {
        // Your logic to get product ID from track ID
        return get_post_meta($track_id, '_product_id', true);
    }
}
```

### JavaScript Integration

For frontend players, you can also track events via JavaScript:

```javascript
// Check if BFA tracking is available
if (window.bfaTrack) {
    // Track play event
    window.bfaTrack('music_play', {
        object_id: productId,
        meta: {
            track_id: trackId,
            track_title: trackTitle,
            artist: artistName,
            duration: trackDuration
        }
    });
    
    // Track progress
    window.bfaTrack('music_progress', {
        object_id: productId,
        value: percentComplete,
        meta: {
            track_id: trackId,
            current_time: currentTime,
            total_time: totalTime
        }
    });
}
```

## WooCommerce Integration

### Track Custom Product Events

```php
// Track product quick view
add_action('woocommerce_quick_view', function($product_id) {
    do_action('bfa_track', 'product_quick_view', [
        'object_id' => $product_id,
        'meta' => [
            'source' => 'shop_page',
        ]
    ]);
});

// Track wishlist additions
add_action('yith_wcwl_added_to_wishlist', function($product_id) {
    do_action('bfa_track', 'add_to_wishlist', [
        'object_id' => $product_id,
    ]);
});

// Track product comparisons
add_action('woocommerce_products_compare', function($product_ids) {
    foreach ($product_ids as $product_id) {
        do_action('bfa_track', 'product_compare', [
            'object_id' => $product_id,
            'value' => count($product_ids),
        ]);
    }
});
```

### Display Analytics in Product Admin

```php
// Add play count column to products list
add_filter('manage_product_posts_columns', function($columns) {
    $columns['bfa_plays'] = __('Plays', 'your-plugin');
    return $columns;
});

add_action('manage_product_posts_custom_column', function($column, $post_id) {
    if ($column === 'bfa_plays') {
        $plays = apply_filters('bfa_get_play_count', 0, $post_id);
        echo '<strong>' . number_format($plays) . '</strong>';
    }
}, 10, 2);
```

## Bandfront Members Integration

### Complete Integration Example for Bandfront Members Plugin

```php
class BandfrontMembers_Analytics {
    
    public function __construct() {
        // Track member lifecycle events
        add_action('user_register', [$this, 'trackMemberJoin'], 10, 1);
        add_action('wp_login', [$this, 'trackMemberLogin'], 10, 2);
        add_action('bandfront_member_tier_change', [$this, 'trackTierChange'], 10, 3);
        add_action('bandfront_member_subscription_start', [$this, 'trackSubscriptionStart'], 10, 2);
        add_action('bandfront_member_subscription_cancel', [$this, 'trackSubscriptionCancel'], 10, 2);
        
        // Track member content access
        add_action('bandfront_member_content_access', [$this, 'trackContentAccess'], 10, 3);
        add_action('bandfront_member_download', [$this, 'trackMemberDownload'], 10, 3);
        
        // Add analytics data to member profiles
        add_filter('bandfront_member_profile_data', [$this, 'addAnalyticsData'], 10, 2);
    }
    
    /**
     * Track when a new member joins
     */
    public function trackMemberJoin($user_id) {
        if (!has_action('bfa_track')) {
            return;
        }
        
        $user = get_userdata($user_id);
        
        do_action('bfa_track', 'member_join', [
            'object_id' => $user_id,
            'meta' => [
                'user_email_hash' => md5($user->user_email),
                'registration_source' => $_SERVER['HTTP_REFERER'] ?? 'direct',
                'initial_role' => implode(',', $user->roles),
            ]
        ]);
    }
    
    /**
     * Track member login with additional context
     */
    public function trackMemberLogin($user_login, $user) {
        if (!has_action('bfa_track')) {
            return;
        }
        
        // Get member tier if available
        $member_tier = get_user_meta($user->ID, 'bandfront_member_tier', true);
        
        do_action('bfa_track', 'member_login', [
            'object_id' => $user->ID,
            'meta' => [
                'member_tier' => $member_tier ?: 'free',
                'login_method' => isset($_POST['loginmethod']) ? $_POST['loginmethod'] : 'standard',
                'days_since_join' => $this->getDaysSinceJoin($user->ID),
            ]
        ]);
    }
    
    /**
     * Track membership tier changes
     */
    public function trackTierChange($user_id, $old_tier, $new_tier) {
        if (!has_action('bfa_track')) {
            return;
        }
        
        do_action('bfa_track', 'member_tier_change', [
            'object_id' => $user_id,
            'meta' => [
                'old_tier' => $old_tier,
                'new_tier' => $new_tier,
                'change_type' => $this->getTierChangeType($old_tier, $new_tier),
                'tier_value_change' => $this->getTierValue($new_tier) - $this->getTierValue($old_tier),
            ]
        ]);
    }
    
    /**
     * Track subscription starts
     */
    public function trackSubscriptionStart($user_id, $subscription_data) {
        if (!has_action('bfa_track')) {
            return;
        }
        
        do_action('bfa_track', 'member_subscription_start', [
            'object_id' => $user_id,
            'value' => $subscription_data['amount'] ?? 0,
            'meta' => [
                'subscription_id' => $subscription_data['id'],
                'tier' => $subscription_data['tier'],
                'billing_period' => $subscription_data['period'],
                'payment_method' => $subscription_data['payment_method'],
            ]
        ]);
    }
    
    /**
     * Track member content access
     */
    public function trackContentAccess($user_id, $content_id, $content_type) {
        if (!has_action('bfa_track')) {
            return;
        }
        
        do_action('bfa_track', 'member_content_access', [
            'object_id' => $content_id,
            'meta' => [
                'user_id' => $user_id,
                'content_type' => $content_type,
                'member_tier' => get_user_meta($user_id, 'bandfront_member_tier', true),
                'access_method' => 'membership', // vs 'purchase', 'free', etc.
            ]
        ]);
    }
    
    /**
     * Track member-exclusive downloads
     */
    public function trackMemberDownload($user_id, $file_id, $product_id) {
        if (!has_action('bfa_track')) {
            return;
        }
        
        do_action('bfa_track', 'member_download', [
            'object_id' => $product_id,
            'meta' => [
                'user_id' => $user_id,
                'file_id' => $file_id,
                'member_tier' => get_user_meta($user_id, 'bandfront_member_tier', true),
                'download_source' => 'member_area',
            ]
        ]);
    }
    
    /**
     * Add analytics data to member profiles
     */
    public function addAnalyticsData($profile_data, $user_id) {
        if (!has_filter('bfa_get_post_views')) {
            return $profile_data;
        }
        
        // Get member's content engagement
        $member_stats = $this->getMemberStats($user_id);
        
        $profile_data['analytics'] = [
            'total_logins' => $member_stats['login_count'],
            'content_views' => $member_stats['content_views'],
            'last_active' => $member_stats['last_active'],
            'engagement_score' => $this->calculateEngagementScore($member_stats),
        ];
        
        return $profile_data;
    }
    
    /**
     * Get member statistics from analytics
     */
    private function getMemberStats($user_id) {
        global $wpdb;
        
        // This assumes direct database access - adjust based on your needs
        $stats = $wpdb->get_row($wpdb->prepare("
            SELECT 
                COUNT(CASE WHEN event_type = 'member_login' THEN 1 END) as login_count,
                COUNT(CASE WHEN event_type = 'member_content_access' THEN 1 END) as content_views,
                MAX(created_at) as last_active
            FROM {$wpdb->prefix}bfa_events
            WHERE object_id = %d 
            AND event_type LIKE 'member_%'
        ", $user_id), ARRAY_A);
        
        return $stats ?: [
            'login_count' => 0,
            'content_views' => 0,
            'last_active' => null,
        ];
    }
    
    /**
     * Calculate days since member joined
     */
    private function getDaysSinceJoin($user_id) {
        $user = get_userdata($user_id);
        $registered = new DateTime($user->user_registered);
        $now = new DateTime();
        return $registered->diff($now)->days;
    }
    
    /**
     * Determine tier change type
     */
    private function getTierChangeType($old_tier, $new_tier) {
        $old_value = $this->getTierValue($old_tier);
        $new_value = $this->getTierValue($new_tier);
        
        if ($new_value > $old_value) return 'upgrade';
        if ($new_value < $old_value) return 'downgrade';
        return 'lateral';
    }
    
    /**
     * Get numeric value for tier (for comparison)
     */
    private function getTierValue($tier) {
        $tier_values = [
            'free' => 0,
            'bronze' => 1,
            'silver' => 2,
            'gold' => 3,
            'platinum' => 4,
        ];
        
        return $tier_values[$tier] ?? 0;
    }
    
    /**
     * Calculate member engagement score
     */
    private function calculateEngagementScore($stats) {
        $score = 0;
        $score += min($stats['login_count'] * 2, 50); // Max 50 points for logins
        $score += min($stats['content_views'] * 3, 50); // Max 50 points for content views
        
        return min($score, 100); // Cap at 100
    }
}

// Initialize the integration
new BandfrontMembers_Analytics();
```

### Retrieving Member Analytics Data

```php
// Get member-specific analytics
function get_member_analytics($user_id) {
    // Get total content views by this member
    $content_views = apply_filters('bfa_get_member_stat', 0, [
        'user_id' => $user_id,
        'stat' => 'content_views',
        'days' => 30,
    ]);
    
    // Get member's favorite content (most viewed)
    $favorite_content = apply_filters('bfa_get_member_favorites', [], [
        'user_id' => $user_id,
        'limit' => 5,
    ]);
    
    // Get member activity timeline
    $activity = apply_filters('bfa_get_member_activity', [], [
        'user_id' => $user_id,
        'days' => 7,
    ]);
    
    return [
        'views' => $content_views,
        'favorites' => $favorite_content,
        'recent_activity' => $activity,
    ];
}
```

### Member Dashboard Widget

```php
// Add analytics widget to member dashboard
add_action('bandfront_member_dashboard_widgets', function($user_id) {
    $stats = get_member_analytics($user_id);
    ?>
    <div class="bfm-analytics-widget">
        <h3><?php _e('Your Activity', 'bandfront-members'); ?></h3>
        <ul>
            <li><?php printf(__('Content views this month: %d', 'bandfront-members'), $stats['views']); ?></li>
            <li><?php printf(__('Favorite artist: %s', 'bandfront-members'), $stats['favorites'][0]['title'] ?? 'N/A'); ?></li>
        </ul>
    </div>
    <?php
});
```

### Bulk Member Import Tracking

```php
// Track bulk member imports
add_action('bandfront_members_bulk_import_complete', function($import_data) {
    if (!has_action('bfa_track')) {
        return;
    }
    
    do_action('bfa_track', 'member_bulk_import', [
        'object_id' => 0, // No specific object
        'value' => $import_data['count'],
        'meta' => [
            'import_source' => $import_data['source'],
            'success_count' => $import_data['success'],
            'error_count' => $import_data['errors'],
            'import_id' => $import_data['id'],
        ]
    ]);
});
```

## Best Practices

### 1. Always Check for Analytics Availability

```php
if (!has_action('bfa_track')) {
    // Analytics not available, handle gracefully
    return;
}
```

### 2. Use Descriptive Event Names

```php
// Good - prefixed with context
do_action('bfa_track', 'member_tier_upgrade', [...]);
do_action('bfa_track', 'member_content_unlock', [...]);

// Avoid - too generic
do_action('bfa_track', 'upgrade', [...]);
do_action('bfa_track', 'unlock', [...]);
```

### 3. Include Relevant Metadata

```php
do_action('bfa_track', 'form_submission', [
    'object_id' => $form_id,
    'meta' => [
        'form_type' => 'contact',
        'fields_count' => 5,
        'has_file_upload' => true,
        'source_page' => get_the_ID(),
    ]
]);
```

### 4. Respect User Privacy

```php
// Don't track sensitive data
do_action('bfa_track', 'user_action', [
    'object_id' => $user_id,
    'meta' => [
        'action' => 'profile_update',
        // Don't include email, passwords, etc.
    ]
]);
```

### 5. Use Consistent Object IDs

Always use WordPress post/product/user IDs as object_id when possible for better data correlation.

## API Reference

### Actions

| Action | Parameters | Description |
|--------|------------|-------------|
| `bfa_track` | `$event_type`, `$data` | Track any custom event |
| `bfa_track_pageview` | `$post_id` | Track a pageview |
| `bfa_track_music_play` | `$track_id`, `$product_id` | Track music play |
| `bfa_track_music_complete` | `$track_id`, `$product_id` | Track music completion |
| `bfa_track_download` | `$file_id`, `$product_id` | Track file download |

### Filters

| Filter | Parameters | Returns | Description |
|--------|------------|---------|-------------|
| `bfa_get_play_count` | `$default`, `$product_id` | int | Get play count for a product |
| `bfa_get_post_views` | `$default`, `$post_id` | int | Get view count for a post |
| `bfa_get_trending` | `$default`, `$args` | array | Get trending items |
| `bfa_get_quick_stats` | `$stats` | array | Get/modify quick statistics |
| `bfa_get_member_stat` | `$default`, `$args` | mixed | Get member-specific statistics |
| `bfa_get_member_favorites` | `$default`, `$args` | array | Get member's most viewed content |
| `bfa_get_member_activity` | `$default`, `$args` | array | Get member's activity timeline |

### Event Data Structure

```php
$data = [
    'object_id' => 123,              // Required: ID of the object
    'object_type' => 'product',      // Optional: Type of object
    'value' => 29.99,                // Optional: Numeric value
    'meta' => [                      // Optional: Additional metadata
        'key' => 'value',
        // ... any additional data
    ]
];
```

## Testing and Development

### Using Test Data Generation

Bandfront Analytics includes built-in tools for generating test data to help you develop and test your integrations:

```php
// Check if test utilities are available
if (class_exists('BandfrontAnalytics\Utils\DbTest')) {
    // Generate 100 test events
    $result = \BandfrontAnalytics\Utils\DbTest::generateTestEvents(100);
    
    echo "Generated {$result['generated']} test events";
}
```

### Cleaning Test Data

After testing, you can clean up all test data:

```php
// Clean all test events
if (class_exists('BandfrontAnalytics\Utils\DbClean')) {
    $result = \BandfrontAnalytics\Utils\DbClean::cleanTestEvents();
    
    echo "Cleaned {$result['events_deleted']} test events";
}
```

### Identifying Test Events

All test events include a `test_event: true` flag in their metadata:

```php
// When tracking events in development, mark them as test events
do_action('bfa_track', 'my_custom_event', [
    'object_id' => 123,
    'meta' => [
        'test_event' => true,  // This marks it for easy cleanup
        'other_data' => 'value'
    ]
]);
```

### Development Best Practices

1. **Always mark test events**: Include `'test_event' => true` in metadata during development
2. **Use the Database Monitor**: Check Analytics > Database Monitor to see your events in real-time
3. **Clean up regularly**: Use the clean function or the UI button to remove test data
4. **Test with realistic data**: The test generator creates realistic event patterns

## Support

For questions or issues with integration:
1. Check the [GitHub repository](https://github.com/bandfront/analytics)
2. Review the [FAQ](https://bandfront.com/analytics/faq)
3. Contact support at analytics@bandfront.com
