<?php

return [
    'enabled' => env('DB_MONITOR_ENABLED', true),

    /*
    | Fraction of requests / jobs to monitor (0.0 – 1.0). Lower it in
    | production to reduce overhead, e.g. 0.1 monitors 10% of traffic.
    */
    'sample_rate' => env('DB_MONITOR_SAMPLE_RATE', 1.0),

    'slow_query_threshold_ms' => env('DB_MONITOR_SLOW_THRESHOLD', 500),

    'n_plus_one_threshold' => env('DB_MONITOR_N1_THRESHOLD', 10),

    'duplicate_query_threshold' => env('DB_MONITOR_DUPLICATE_THRESHOLD', 3),

    'max_queries_per_request' => env('DB_MONITOR_MAX_QUERIES', 50),

    'missing_index_min_occurrences' => env('DB_MONITOR_INDEX_THRESHOLD', 50),

    /*
    | Built-in detectors. Set any of them to false to turn it off.
    */
    'detectors' => [
        'slow_query' => true,
        'n_plus_one' => true,
        'duplicate_query' => true,
        'too_many_queries' => true,
        'missing_index' => true,
    ],

    /*
    | Your own detectors: class names implementing
    | BenjdiaSaad\DbMonitor\Detectors\DetectorInterface.
    */
    'custom_detectors' => [],

    'store_queries' => env('DB_MONITOR_STORE_QUERIES', true),

    /*
    | Record the app file:line that triggered each query (skips vendor frames).
    */
    'capture_location' => env('DB_MONITOR_CAPTURE_LOCATION', true),

    'retention_days' => env('DB_MONITOR_RETENTION', 7),

    /*
    | Automatically push the MonitorQueries middleware onto the global
    | HTTP stack, so you don't have to register it yourself.
    */
    'auto_register_middleware' => env('DB_MONITOR_AUTO_MIDDLEWARE', false),

    /*
    | Monitor queued jobs as well as HTTP requests.
    */
    'monitor_jobs' => env('DB_MONITOR_JOBS', false),

    /*
    | An email address, or a map of channel => route, e.g.
    | ['mail' => 'ops@example.com', 'slack' => 'https://hooks.slack.com/...'].
    */
    'notify' => env('DB_MONITOR_NOTIFY', null),

    'notification_channels' => ['mail'],

    /*
    | Lowest severity that triggers a notification: "warning" or "critical".
    */
    'notify_min_severity' => env('DB_MONITOR_NOTIFY_SEVERITY', 'critical'),

    /*
    | Don't re-send a notification for the same issue within this many minutes.
    */
    'notification_throttle_minutes' => env('DB_MONITOR_NOTIFY_THROTTLE', 60),

    'exclude_paths' => [
        'telescope/*',
        '_debugbar/*',
        'horizon/*',
        'livewire/*',
        'pulse/*',
    ],

    /*
    | Regular expressions matched against SQL. Matching queries are ignored.
    */
    'ignore_queries' => [
        // '/^select \* from `sessions`/i',
    ],
];
