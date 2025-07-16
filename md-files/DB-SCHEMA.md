# Bandfront Analytics - Database Schema Documentation

## Overview

Bandfront Analytics uses a streamlined database architecture designed for high-performance event tracking and efficient data aggregation. The schema is optimized for write-heavy workloads while maintaining fast query performance for analytics reporting.

## Table of Contents

1. [Database Tables](#database-tables)
2. [Table Schemas](#table-schemas)
3. [Indexes and Performance](#indexes-and-performance)
4. [Data Types and Constraints](#data-types-and-constraints)
5. [Relationships](#relationships)
6. [Data Retention](#data-retention)
7. [Query Examples](#query-examples)
8. [Migration Guide](#migration-guide)

## Database Tables

The plugin creates two main tables:

| Table Name | Purpose | Row Growth |
|------------|---------|------------|
| `{prefix}bfa_events` | Stores all analytics events | High (thousands/day) |
| `{prefix}bfa_stats` | Stores aggregated statistics | Moderate (hundreds/day) |

## Table Schemas

### 1. Events Table (`{prefix}bfa_events`)

The main event storage table that records all analytics activities.

```sql
CREATE TABLE {prefix}bfa_events (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    event_type VARCHAR(50) NOT NULL,
    object_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
    object_type VARCHAR(50) DEFAULT NULL,
    value DECIMAL(10,2) DEFAULT NULL,
    meta_data LONGTEXT DEFAULT NULL,
    session_id VARCHAR(64) DEFAULT NULL,
    user_hash VARCHAR(64) DEFAULT NULL,
    referrer_domain VARCHAR(255) DEFAULT NULL,
    user_agent_hash VARCHAR(64) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_event_type (event_type),
    KEY idx_object (object_id, object_type),
    KEY idx_created_at (created_at),
    KEY idx_session (session_id),
    KEY idx_user_hash (user_hash)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

#### Column Descriptions:

| Column | Type | Description | Example |
|--------|------|-------------|---------|
| `id` | BIGINT UNSIGNED | Primary key, auto-incrementing | 12345 |
| `event_type` | VARCHAR(50) | Type of event being tracked | 'pageview', 'music_play' |
| `object_id` | BIGINT UNSIGNED | WordPress object ID (post, product, user) | 456 |
| `object_type` | VARCHAR(50) | Type of WordPress object | 'post', 'product', 'user' |
| `value` | DECIMAL(10,2) | Numeric value (duration, amount, etc.) | 29.99 |
| `meta_data` | LONGTEXT | JSON-encoded additional data | '{"track_id":123}' |
| `session_id` | VARCHAR(64) | Hashed session identifier | 'a1b2c3d4...' |
| `user_hash` | VARCHAR(64) | Hashed user identifier (privacy-safe) | 'e5f6g7h8...' |
| `referrer_domain` | VARCHAR(255) | Referring domain | 'google.com' |
| `user_agent_hash` | VARCHAR(64) | Hashed user agent | 'i9j0k1l2...' |
| `created_at` | TIMESTAMP | Event timestamp | '2024-01-15 10:30:00' |

### 2. Statistics Table (`{prefix}bfa_stats`)

Aggregated statistics for faster reporting and reduced query load.

```sql
CREATE TABLE {prefix}bfa_stats (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    stat_date DATE NOT NULL,
    stat_type VARCHAR(50) NOT NULL,
    object_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
    object_type VARCHAR(50) DEFAULT NULL,
    event_count INT UNSIGNED NOT NULL DEFAULT 0,
    unique_visitors INT UNSIGNED NOT NULL DEFAULT 0,
    total_value DECIMAL(12,2) DEFAULT NULL,
    meta_data LONGTEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY idx_unique_stat (stat_date, stat_type, object_id, object_type),
    KEY idx_stat_date (stat_date),
    KEY idx_stat_type (stat_type),
    KEY idx_object (object_id, object_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

#### Column Descriptions:

| Column | Type | Description | Example |
|--------|------|-------------|---------|
| `id` | BIGINT UNSIGNED | Primary key | 789 |
| `stat_date` | DATE | Date of the statistics | '2024-01-15' |
| `stat_type` | VARCHAR(50) | Type of statistic | 'daily_views', 'play_count' |
| `object_id` | BIGINT UNSIGNED | Related object ID | 456 |
| `object_type` | VARCHAR(50) | Type of object | 'product' |
| `event_count` | INT UNSIGNED | Number of events | 1523 |
| `unique_visitors` | INT UNSIGNED | Unique visitor count | 892 |
| `total_value` | DECIMAL(12,2) | Sum of all values | 4567.89 |
| `meta_data` | LONGTEXT | Additional aggregated data | '{"avg_duration":125}' |
| `created_at` | TIMESTAMP | Record creation time | '2024-01-15 00:00:00' |
| `updated_at` | TIMESTAMP | Last update time | '2024-01-15 23:59:59' |

## Indexes and Performance

### Primary Indexes

1. **Events Table Indexes:**
   - `PRIMARY KEY (id)` - Clustered index for fast lookups
   - `idx_event_type` - Fast filtering by event type
   - `idx_object` - Composite index for object queries
   - `idx_created_at` - Time-based queries and cleanup
   - `idx_session` - Session analysis
   - `idx_user_hash` - User activity tracking

2. **Stats Table Indexes:**
   - `PRIMARY KEY (id)` - Primary identifier
   - `idx_unique_stat` - Prevents duplicate aggregations
   - `idx_stat_date` - Date range queries
   - `idx_stat_type` - Filter by statistic type
   - `idx_object` - Object-specific stats

### Query Performance Tips

```sql
-- Good: Uses indexes effectively
SELECT COUNT(*) FROM {prefix}bfa_events 
WHERE event_type = 'pageview' 
AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY);

-- Good: Composite index usage
SELECT * FROM {prefix}bfa_events 
WHERE object_id = 123 AND object_type = 'product';

-- Avoid: Full table scan
SELECT * FROM {prefix}bfa_events 
WHERE JSON_EXTRACT(meta_data, '$.custom_field') = 'value';
```

## Data Types and Constraints

### Event Types (Common Values)

| Event Type | Description | Typical Value | Meta Data |
|------------|-------------|---------------|-----------|
| `pageview` | Page view | null | page_type, author_id |
| `music_play` | Music play started | null | track_id, autoplay |
| `music_complete` | Track finished | 100 (percent) | track_id, duration |
| `download` | File downloaded | file_size | file_id, file_type |
| `user_login` | User logged in | null | user_role |
| `purchase` | Product purchased | amount | order_id, quantity |
| `add_to_cart` | Added to cart | quantity | variation_id |
| `scroll` | Page scroll depth | percent | max_depth |
| `time_on_page` | Time spent | seconds | bounce |

### Object Types

| Object Type | Description | Typical IDs |
|-------------|-------------|-------------|
| `post` | Blog posts | WordPress post IDs |
| `page` | Static pages | WordPress page IDs |
| `product` | WooCommerce products | Product IDs |
| `user` | WordPress users | User IDs |
| `category` | Categories | Term IDs |
| `attachment` | Media files | Attachment IDs |

## Relationships

### Entity Relationship Diagram

```
┌─────────────────┐
│   WP Posts      │
│   (object_id)   │◄────────┐
└─────────────────┘         │
                            │
┌─────────────────┐         │      ┌─────────────────┐
│   BFA Events    │─────────┴──────│   BFA Stats     │
│                 │                 │                 │
│ - object_id     │                 │ - object_id     │
│ - object_type   │◄────Aggregated──│ - object_type   │
│ - event_type    │                 │ - stat_type     │
└─────────────────┘                 └─────────────────┘
         │
         │
         ▼
┌─────────────────┐
│   WP Users      │
│   (user_hash)   │
└─────────────────┘
```

### Data Flow

1. **Event Recording**: User action → JavaScript/PHP → `bfa_events` table
2. **Aggregation**: Cron job → Read `bfa_events` → Write `bfa_stats`
3. **Reporting**: Admin dashboard → Read `bfa_stats` → Display charts

## Data Retention

### Retention Policies

```sql
-- Default retention: 365 days for events
DELETE FROM {prefix}bfa_events 
WHERE created_at < DATE_SUB(NOW(), INTERVAL 365 DAY);

-- Stats are kept indefinitely by default
-- But can be cleaned up for old, low-value data
DELETE FROM {prefix}bfa_stats 
WHERE stat_date < DATE_SUB(NOW(), INTERVAL 730 DAY)
AND event_count < 10;
```

### Archival Strategy

For long-term storage, consider:

```sql
-- Create archive table
CREATE TABLE {prefix}bfa_events_archive LIKE {prefix}bfa_events;

-- Move old data
INSERT INTO {prefix}bfa_events_archive 
SELECT * FROM {prefix}bfa_events 
WHERE created_at < DATE_SUB(NOW(), INTERVAL 90 DAY);

-- Remove from main table
DELETE FROM {prefix}bfa_events 
WHERE created_at < DATE_SUB(NOW(), INTERVAL 90 DAY);
```

## Query Examples

### Common Analytics Queries

#### 1. Get Today's Pageviews
```sql
SELECT COUNT(*) as views, 
       COUNT(DISTINCT session_id) as visitors
FROM {prefix}bfa_events
WHERE event_type = 'pageview'
AND DATE(created_at) = CURDATE();
```

#### 2. Top 10 Products by Plays
```sql
SELECT 
    object_id,
    COUNT(*) as play_count,
    COUNT(DISTINCT session_id) as unique_listeners
FROM {prefix}bfa_events
WHERE event_type = 'music_play'
AND object_type = 'product'
AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
GROUP BY object_id
ORDER BY play_count DESC
LIMIT 10;
```

#### 3. User Engagement Timeline
```sql
SELECT 
    DATE(created_at) as date,
    COUNT(DISTINCT session_id) as daily_users,
    COUNT(*) as total_events,
    COUNT(CASE WHEN event_type = 'pageview' THEN 1 END) as pageviews,
    COUNT(CASE WHEN event_type = 'music_play' THEN 1 END) as plays
FROM {prefix}bfa_events
WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
GROUP BY DATE(created_at)
ORDER BY date DESC;
```

#### 4. Conversion Funnel
```sql
WITH funnel AS (
    SELECT 
        session_id,
        MAX(CASE WHEN event_type = 'pageview' THEN 1 ELSE 0 END) as viewed,
        MAX(CASE WHEN event_type = 'add_to_cart' THEN 1 ELSE 0 END) as added_cart,
        MAX(CASE WHEN event_type = 'purchase' THEN 1 ELSE 0 END) as purchased
    FROM {prefix}bfa_events
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    GROUP BY session_id
)
SELECT 
    SUM(viewed) as page_views,
    SUM(added_cart) as cart_adds,
    SUM(purchased) as purchases,
    ROUND(SUM(added_cart) / SUM(viewed) * 100, 2) as cart_rate,
    ROUND(SUM(purchased) / SUM(added_cart) * 100, 2) as purchase_rate
FROM funnel;
```

#### 5. Average Session Duration
```sql
SELECT 
    AVG(session_duration) as avg_duration_seconds,
    MAX(session_duration) as max_duration_seconds,
    COUNT(*) as total_sessions
FROM (
    SELECT 
        session_id,
        TIMESTAMPDIFF(SECOND, MIN(created_at), MAX(created_at)) as session_duration
    FROM {prefix}bfa_events
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 1 DAY)
    GROUP BY session_id
    HAVING COUNT(*) > 1
) as sessions;
```

## Migration Guide

### Upgrading from Previous Versions

```sql
-- Check current schema version
SELECT option_value 
FROM {prefix}options 
WHERE option_name = 'bfa_db_version';

-- Add new columns (if upgrading)
ALTER TABLE {prefix}bfa_events 
ADD COLUMN user_agent_hash VARCHAR(64) DEFAULT NULL AFTER referrer_domain,
ADD INDEX idx_user_agent (user_agent_hash);

-- Update schema version
UPDATE {prefix}options 
SET option_value = '2.0.0' 
WHERE option_name = 'bfa_db_version';
```

### Performance Optimization

```sql
-- Analyze table statistics
ANALYZE TABLE {prefix}bfa_events, {prefix}bfa_stats;

-- Optimize tables (use with caution on large tables)
OPTIMIZE TABLE {prefix}bfa_events, {prefix}bfa_stats;

-- Check table sizes
SELECT 
    table_name,
    ROUND(data_length / 1024 / 1024, 2) AS data_size_mb,
    ROUND(index_length / 1024 / 1024, 2) AS index_size_mb,
    table_rows
FROM information_schema.tables
WHERE table_schema = DATABASE()
AND table_name LIKE '{prefix}bfa_%';
```

### Backup Recommendations

```bash
# Daily backup of events (last 7 days only)
mysqldump -u user -p database_name {prefix}bfa_events \
  --where="created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)" \
  > bfa_events_recent.sql

# Full stats backup (smaller table)
mysqldump -u user -p database_name {prefix}bfa_stats > bfa_stats_full.sql
```

## Security Considerations

1. **Data Privacy**: All user identifiers are hashed
2. **SQL Injection**: Use prepared statements for all queries
3. **Data Sanitization**: JSON data in meta_data is always escaped
4. **Access Control**: Tables use WordPress database prefix
5. **Rate Limiting**: Consider implementing at application level

## Troubleshooting

### Common Issues

1. **Slow Queries**: Check indexes and use EXPLAIN
2. **Table Growth**: Implement regular cleanup jobs
3. **Lock Contention**: Consider partitioning for very large sites
4. **Character Encoding**: Ensure utf8mb4 for emoji support

### Monitoring Queries

```sql
-- Check table health
SHOW TABLE STATUS LIKE '{prefix}bfa_%';

-- Find slow queries
SELECT * FROM {prefix}bfa_events 
WHERE created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
ORDER BY id DESC LIMIT 100;

-- Session distribution
SELECT 
    COUNT(DISTINCT session_id) as unique_sessions,
    COUNT(*) as total_events,
    COUNT(*) / COUNT(DISTINCT session_id) as avg_events_per_session
FROM {prefix}bfa_events
WHERE created_at >= DATE_SUB(NOW(), INTERVAL 1 DAY);
```
