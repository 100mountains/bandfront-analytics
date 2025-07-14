# Bandfront Analytics API Reference

This document provides a comprehensive reference for the Bandfront Analytics API, including all available endpoints, required parameters, authentication, response formats, and integration examples.

## Table of Contents

1. [Authentication](#authentication)
2. [Base URL](#base-url)
3. [API Endpoints](#api-endpoints)
   - [Track Event](#track-event)
   - [Get Stats](#get-stats)
   - [Quick Stats](#quick-stats)
   - [Chart Data](#chart-data)
   - [Top Posts](#top-posts)
   - [Top Tracks](#top-tracks)
   - [Member Growth](#member-growth)
   - [Member Activity](#member-activity)
4. [Integration Examples](#integration-examples)
   - [Tracking Pageviews](#tracking-pageviews)
   - [Tracking Music Plays](#tracking-music-plays)
   - [Member Activity Tracking](#member-activity-tracking)
5. [Error Handling](#error-handling)
6. [Rate Limiting](#rate-limiting)
7. [Data Sampling](#data-sampling)

## Authentication

All API requests to protected endpoints require WordPress REST API authentication using a nonce. The nonce must be included in the `X-WP-Nonce` header.

```javascript
// Example of setting the nonce header
const headers = {
  'Content-Type': 'application/json',
  'X-WP-Nonce': bfaTracker.nonce // Provided by the plugin via wp_localize_script
};
```

Public tracking endpoints (like `/track`) don't require authentication to allow anonymous usage tracking, but they use nonces to prevent CSRF attacks.

## Base URL

All API endpoints are relative to the WordPress REST API URL with the `bandfront-analytics/v1` namespace:

```
https://your-site.com/wp-json/bandfront-analytics/v1/
```

In JavaScript, this is typically available as:

```javascript
const apiUrl = bfaTracker.apiUrl; // Provided by wp_localize_script
```

## API Endpoints

### Track Event

Tracks any analytics event (pageview, music play, etc.)

**Endpoint:** `POST /track`  
**Authentication:** Not required (public endpoint)  
**Parameters:**

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| event_type | string | Yes | Type of event (e.g., 'pageview', 'music_play', 'scroll', 'time_on_page') |
| object_id | integer | No | ID of the related object (post ID, product ID, etc.) |
| value | number | No | Value associated with the event (e.g., scroll depth percentage, play duration) |
| meta | object | No | Additional metadata related to the event |

**Example Request:**

```javascript
fetch(bfaTracker.apiUrl + 'track', {
  method: 'POST',
  headers: {
    'Content-Type': 'application/json',
    'X-WP-Nonce': bfaTracker.nonce
  },
  body: JSON.stringify({
    event_type: 'music_play',
    object_id: 123,
    value: 0,
    meta: {
      track_index: 0,
      track_src: 'https://example.com/music.mp3',
      duration: 180
    }
  })
});
```

**Response:**

```json
{
  "success": true,
  "session_id": "1a2b3c4d5e6f7g8h9i0j"
}
```

### Get Stats

Retrieves aggregated statistics for a specified date range and metric.

**Endpoint:** `GET /stats`  
**Authentication:** Required (admin only)  
**Parameters:**

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| start_date | string | No | Start date in YYYY-MM-DD format (defaults to 7 days ago) |
| end_date | string | No | End date in YYYY-MM-DD format (defaults to today) |
| metric | string | No | Metric to retrieve (default: 'pageviews', options: 'pageviews', 'music_plays', etc.) |

**Example Request:**

```javascript
fetch(bfaAdmin.apiUrl + 'stats?start_date=2023-01-01&end_date=2023-01-31&metric=music_plays', {
  headers: {
    'X-WP-Nonce': bfaAdmin.nonce
  }
});
```

**Response:**

```json
{
  "stats": [
    {"stat_date": "2023-01-01", "value": 150},
    {"stat_date": "2023-01-02", "value": 203},
    {"stat_date": "2023-01-03", "value": 175}
  ],
  "period": {
    "start": "2023-01-01",
    "end": "2023-01-31"
  },
  "metric": "music_plays"
}
```

### Quick Stats

Retrieves summary statistics for the dashboard and admin bar.

**Endpoint:** `GET /quick-stats`  
**Authentication:** Required (admin only)  
**Parameters:** None

**Example Request:**

```javascript
fetch(bfaAdmin.apiUrl + 'quick-stats', {
  headers: {
    'X-WP-Nonce': bfaAdmin.nonce
  }
});
```

**Response:**

```json
{
  "today_views": 1250,
  "yesterday_views": 1100,
  "today_visitors": 780,
  "active_users": 42,
  "today_plays": 320
}
```

### Chart Data

Retrieves formatted data for Chart.js charts.

**Endpoint:** `GET /chart`  
**Authentication:** Required (admin only)  
**Parameters:**

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| days | integer | No | Number of days to include (default: 7) |
| metric | string | No | Metric to retrieve (default: 'pageviews', options: 'pageviews', 'music_plays') |

**Example Request:**

```javascript
fetch(bfaAdmin.apiUrl + 'chart?days=30&metric=music_plays', {
  headers: {
    'X-WP-Nonce': bfaAdmin.nonce
  }
});
```

**Response:**

```json
{
  "labels": ["Jan 1", "Jan 2", "Jan 3", "Jan 4"],
  "datasets": [
    {
      "label": "Music Plays",
      "data": [150, 203, 175, 192],
      "borderColor": "#8B5CF6",
      "backgroundColor": "rgba(139, 92, 246, 0.1)",
      "tension": 0.1
    }
  ]
}
```

### Top Posts

Retrieves the most viewed posts/pages.

**Endpoint:** `GET /top-posts`  
**Authentication:** Required (admin only)  
**Parameters:**

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| days | integer | No | Number of days to include (default: 7) |
| limit | integer | No | Maximum number of posts to return (default: 10) |

**Example Request:**

```javascript
fetch(bfaAdmin.apiUrl + 'top-posts?days=30&limit=5', {
  headers: {
    'X-WP-Nonce': bfaAdmin.nonce
  }
});
```

**Response:**

```json
[
  {
    "object_id": 123,
    "views": 1520,
    "title": "Sample Post Title",
    "url": "https://example.com/sample-post",
    "type": "post"
  },
  {
    "object_id": 456,
    "views": 980,
    "title": "Another Post",
    "url": "https://example.com/another-post",
    "type": "post"
  }
]
```

### Top Tracks

Retrieves the most played audio tracks.

**Endpoint:** `GET /top-tracks`  
**Authentication:** Required (admin only)  
**Parameters:**

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| days | integer | No | Number of days to include (default: 7) |
| limit | integer | No | Maximum number of tracks to return (default: 10) |

**Example Request:**

```javascript
fetch(bfaAdmin.apiUrl + 'top-tracks?days=30&limit=5', {
  headers: {
    'X-WP-Nonce': bfaAdmin.nonce
  }
});
```

**Response:**

```json
[
  {
    "object_id": 789,
    "plays": 320,
    "title": "Sample Track Title",
    "url": "https://example.com/sample-track",
    "avg_duration": "2:45"
  },
  {
    "object_id": 101,
    "plays": 250,
    "title": "Another Track",
    "url": "https://example.com/another-track",
    "avg_duration": "3:15"
  }
]
```

### Member Growth

Retrieves member growth data for the specified period.

**Endpoint:** `GET /member-growth`  
**Authentication:** Required (admin only)  
**Parameters:**

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| days | integer | No | Number of days to include (default: 7) |

**Example Request:**

```javascript
fetch(bfaAdmin.apiUrl + 'member-growth?days=90', {
  headers: {
    'X-WP-Nonce': bfaAdmin.nonce
  }
});
```

**Response:**

```json
{
  "labels": ["Jan 1", "Jan 2", "Jan 3", "Jan 4"],
  "datasets": [
    {
      "label": "Total Members",
      "data": [150, 153, 158, 162],
      "borderColor": "#0073aa",
      "backgroundColor": "rgba(0, 115, 170, 0.1)",
      "tension": 0.1
    },
    {
      "label": "New Members",
      "data": [0, 3, 5, 4],
      "borderColor": "#46b450",
      "backgroundColor": "rgba(70, 180, 80, 0.1)",
      "tension": 0.1,
      "yAxisID": "y1"
    }
  ]
}
```

### Member Activity

Retrieves recent member activity data.

**Endpoint:** `GET /member-activity`  
**Authentication:** Required (admin only)  
**Parameters:**

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| days | integer | No | Number of days to include (default: 7) |
| limit | integer | No | Maximum number of entries to return (default: 10) |

**Example Request:**

```javascript
fetch(bfaAdmin.apiUrl + 'member-activity?days=30&limit=5', {
  headers: {
    'X-WP-Nonce': bfaAdmin.nonce
  }
});
```

**Response:**

```json
[
  {
    "member_id": 42,
    "name": "John Doe",
    "last_active": "2023-04-15 14:30:00",
    "action_count": 25
  },
  {
    "member_id": 57,
    "name": "Jane Smith",
    "last_active": "2023-04-15 12:15:00",
    "action_count": 18
  }
]
```

## Integration Examples

### Tracking Pageviews

To track pageviews from any WordPress plugin:

```javascript
/**
 * Track a pageview from a custom plugin
 */
function trackBandfrontPageview(postId) {
  // Check if Bandfront Analytics is active and tracker is available
  if (typeof window.bfaTrack !== 'function') {
    return;
  }
  
  window.bfaTrack('pageview', {
    object_id: postId,
    meta: {
      page_type: 'custom_plugin',
      referrer: document.referrer,
      viewport: window.innerWidth + 'x' + window.innerHeight
    }
  });
}

// Call the function when your plugin loads a page
document.addEventListener('DOMContentLoaded', function() {
  const postId = document.querySelector('body').dataset.postId;
  if (postId) {
    trackBandfrontPageview(parseInt(postId));
  }
});
```

### Tracking Music Plays

To integrate with Bandfront Player to track music plays:

```javascript
/**
 * Track a music play event from Bandfront Player
 * 
 * @param {Object} event - Play event data
 * @param {number} event.productId - Product/post ID
 * @param {string} event.trackUrl - URL of the audio file
 * @param {number} event.trackIndex - Index of the track in the playlist
 * @param {number} event.duration - Duration of the track in seconds
 */
function trackBandfrontMusicPlay(event) {
  // Check if Bandfront Analytics is active and tracker is available
  if (typeof window.bfaTrack !== 'function') {
    return;
  }
  
  window.bfaTrack('music_play', {
    object_id: event.productId,
    meta: {
      track_index: event.trackIndex,
      track_src: event.trackUrl,
      duration: event.duration
    }
  });
}

// Hook into Bandfront Player events
document.addEventListener('bfp_track_play', function(e) {
  trackBandfrontMusicPlay(e.detail);
});
```

### Member Activity Tracking

To track member-specific activities from Bandfront Members:

```php
/**
 * Track member activity in Bandfront Analytics
 * 
 * @param int $userId User ID
 * @param string $actionType Type of action (login, purchase, etc.)
 * @param array $metadata Additional data about the action
 */
function bfm_track_member_activity($userId, $actionType, $metadata = []) {
    // Check if Bandfront Analytics is active
    if (!function_exists('bfa_track_event')) {
        return false;
    }
    
    // Convert user ID to username or display name
    $user = get_userdata($userId);
    if (!$user) {
        return false;
    }
    
    $eventData = [
        'event_type' => 'member_' . $actionType,
        'object_id' => $userId,
        'meta' => array_merge([
            'user_name' => $user->display_name,
            'user_email_hash' => md5($user->user_email),
            'timestamp' => current_time('mysql')
        ], $metadata)
    ];
    
    // Track event via API
    return bfa_track_event($eventData);
}

// Track member login
add_action('wp_login', function($username, $user) {
    bfm_track_member_activity($user->ID, 'login', [
        'login_source' => isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : 'direct'
    ]);
}, 10, 2);

// Track member purchase
add_action('woocommerce_order_status_completed', function($orderId) {
    $order = wc_get_order($orderId);
    $userId = $order->get_user_id();
    
    if ($userId) {
        bfm_track_member_activity($userId, 'purchase', [
            'order_id' => $orderId,
            'order_total' => $order->get_total(),
            'products' => array_map(function($item) {
                return [
                    'id' => $item->get_product_id(),
                    'name' => $item->get_name()
                ];
            }, $order->get_items())
        ]);
    }
});
```

## Error Handling

The API uses standard HTTP status codes:

- `200 OK` - Request successful
- `400 Bad Request` - Invalid parameters
- `401 Unauthorized` - Authentication required
- `403 Forbidden` - Insufficient permissions
- `404 Not Found` - Endpoint or resource not found
- `500 Server Error` - Internal server error

Error responses include a JSON object with:

```json
{
  "code": "error_code",
  "message": "Human-readable error message",
  "data": { 
    "status": 400 
  }
}
```

## Rate Limiting

By default, the API limits authenticated requests to 100 per minute per IP address. Public tracking endpoints have higher limits (1000 per minute) to accommodate high-traffic scenarios.

When rate limits are exceeded, the API returns:

```
HTTP/1.1 429 Too Many Requests
Retry-After: 60
```

## Data Sampling

For high-traffic sites, Bandfront Analytics implements automatic data sampling to reduce database load and improve performance. The sampling rate is determined by the following settings:

- `sampling_threshold` - Daily pageviews threshold (default: 10,000)
- `sampling_rate` - Sampling rate when threshold exceeded (default: 0.1 = 10%)

When sampling is active, only a percentage of events are recorded, but results are automatically extrapolated in analytics reports to show estimated totals.

The current sampling status can be checked in JavaScript:

```javascript
// Check if current request is being sampled
if (bfaTracker.sampling < 1) {
  console.log(`Sampling active: recording ${bfaTracker.sampling * 100}% of events`);
}
```

Sampling is transparent to API users - all endpoints return extrapolated data based on the sampling rate.
