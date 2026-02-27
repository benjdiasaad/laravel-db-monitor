<?php

return [
    'enabled' => env('DB_MONITOR_ENABLED', true),

    'slow_query_threshold_ms' => env('DB_MONITOR_SLOW_THRESHOLD', 500),
    
    'n_plus_one_threshold' => env('DB_MONITOR_N1_THRESHOLD', 10),

    'missing_index_min_occurrences' => env('DB_MONITOR_INDEX_THRESHOLD', 50),
    
    'store_queries' => env('DB_MONITOR_STORE_QUERIES', true),
    
    'retention_days' => env('DB_MONITOR_RETENTION', 7),
    
    'notify' => env('DB_MONITOR_NOTIFY', null),
    
    'notification_channels' => ['mail'],
    
    'exclude_paths' => [
        'telescope/*',
        '_debugbar/*',
        'horizon/*',
        'livewire/*',
    ],
    
];