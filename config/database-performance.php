<?php

declare(strict_types=1);

$defaultDatabase = (string) env('DB_CONNECTION', 'sqlite');
$databaseMonitorList = (string) env('DB_MONITOR_DATABASES', $defaultDatabase);
$databaseMonitors = array_values(array_filter(array_map('trim', explode(',', $databaseMonitorList)), static fn (string $value): bool => $value !== ''));

return [
    'enabled' => env('DB_PERFORMANCE_MONITORING', true),

    'log_channel' => (string) env('DB_PERFORMANCE_LOG_CHANNEL', 'performance'),

    'listener' => [
        'enabled' => env('DB_PERFORMANCE_LISTENER_ENABLED', true),
        'log_all_queries' => env('DB_PERFORMANCE_LOG_ALL_QUERIES', false),
        'slow_query_ms' => (int) env('DB_PERFORMANCE_SLOW_QUERY_MS', 100),
    ],

    'cumulative' => [
        'enabled' => env('DB_PERFORMANCE_CUMULATIVE_ALERTS', true),
        'slow_total_ms' => (int) env('DB_PERFORMANCE_CUMULATIVE_MS', 500),
    ],

    'query_count' => [
        'enabled' => env('DB_QUERY_COUNT_MIDDLEWARE', false),
        'append_to_web' => env('DB_QUERY_COUNT_APPEND_WEB', false),
        'max_queries' => (int) env('DB_QUERY_COUNT_MAX', 120),
        'duplicate_threshold' => (int) env('DB_QUERY_COUNT_DUPLICATE_THRESHOLD', 2),
        'loop_threshold' => (int) env('DB_QUERY_COUNT_LOOP_THRESHOLD', 3),
    ],

    'audit' => [
        'enabled' => env('DB_PERFORMANCE_AUDIT_ENABLED', true),
        'collect_backtrace' => env('DB_PERFORMANCE_AUDIT_BACKTRACE', true),
        'slow_like_ms' => (int) env('DB_PERFORMANCE_AUDIT_SLOW_LIKE_MS', 200),
        'sort_candidate_ms' => (int) env('DB_PERFORMANCE_AUDIT_SORT_MS', 250),
        'duplicate_threshold' => (int) env('DB_PERFORMANCE_AUDIT_DUPLICATE_THRESHOLD', 2),
        'loop_threshold' => (int) env('DB_PERFORMANCE_AUDIT_LOOP_THRESHOLD', 3),
    ],

    'db_monitor' => [
        'enabled' => env('DB_MONITOR_ENABLED', true),
        'databases' => $databaseMonitors === [] ? [$defaultDatabase] : $databaseMonitors,
        'max_connections' => (int) env('DB_MONITOR_MAX_CONNECTIONS', 40),
        'frequency' => (string) env('DB_MONITOR_FREQUENCY', 'everyFiveMinutes'),
    ],
];
