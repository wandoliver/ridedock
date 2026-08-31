<?php

return [
    'sync' => [
        'page_size' => (int) env('ACTIVITY_SYNC_PAGE_SIZE', 50),
        'overlap_days' => (int) env('ACTIVITY_SYNC_OVERLAP_DAYS', 2),
        'max_pages' => (int) env('ACTIVITY_SYNC_MAX_PAGES', 100),
        'store_source_files' => (bool) env('ACTIVITY_STORE_SOURCE_FILES', true),
        'source_file_disk' => env('ACTIVITY_SOURCE_FILE_DISK', 'activity_sources'),
    ],

    'fit' => [
        'node_binary' => env('FIT_NODE_BINARY', 'node'),
        'parser_script' => base_path('resources/js/fit/parse-fit.js'),
        'max_file_bytes' => (int) env('FIT_MAX_FILE_BYTES', 50 * 1024 * 1024),
        'timeout_seconds' => (int) env('FIT_PARSE_TIMEOUT_SECONDS', 30),
    ],

    'hammerhead' => [
        'priority' => 100,
        'enabled' => (bool) env('HAMMERHEAD_ENABLED', false),
        'client_id' => env('HAMMERHEAD_CLIENT_ID'),
        'client_secret' => env('HAMMERHEAD_CLIENT_SECRET'),
        'redirect_uri' => env('HAMMERHEAD_REDIRECT_URI'),
        'webhook_secret' => env('HAMMERHEAD_WEBHOOK_SECRET'),
        'api_url' => env('HAMMERHEAD_API_URL', 'https://api.hammerhead.io/v1/api'),
        'auth_url' => env('HAMMERHEAD_AUTH_URL', 'https://api.hammerhead.io/v1/auth'),
        'scopes' => ['activity:read'],

        // Hammerhead activity details return distance in metres and duration in milliseconds.
        // Keep these configurable in case the upstream contract changes.
        'distance_multiplier_to_meters' => (float) env('HAMMERHEAD_DISTANCE_MULTIPLIER', 1),
        'duration_multiplier_to_seconds' => (float) env('HAMMERHEAD_DURATION_MULTIPLIER', 0.001),
    ],

    'wahoo' => [
        'priority' => 90,
        'enabled' => (bool) env('WAHOO_ENABLED', false),
        'client_id' => env('WAHOO_CLIENT_ID'),
        'client_secret' => env('WAHOO_CLIENT_SECRET'),
        'redirect_uri' => env('WAHOO_REDIRECT_URI'),
        'api_url' => env('WAHOO_API_URL', 'https://api.wahooligan.com/v1'),
        'auth_url' => env('WAHOO_AUTH_URL', 'https://api.wahooligan.com'),
        'scopes' => array_values(array_filter(preg_split(
            '/\s+/',
            trim((string) env('WAHOO_SCOPES', 'user_read workouts_read offline_data')),
        ) ?: [])),
        'environment' => env('WAHOO_ENVIRONMENT', 'sandbox'),
        'confidential' => (bool) env('WAHOO_CONFIDENTIAL', true),
        'webhook_enabled' => (bool) env('WAHOO_WEBHOOK_ENABLED', false),
        'webhook_uri' => env('WAHOO_WEBHOOK_URI'),
        'webhook_token' => env('WAHOO_WEBHOOK_TOKEN'),
        'allow_external_sharing' => (bool) env('WAHOO_ALLOW_EXTERNAL_SHARING', false),
    ],
];
