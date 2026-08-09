<?php

return [
    'binary' => env('XRAY_BINARY', '/usr/local/x-ui/bin/xray-linux-amd64'),
    'api_address' => env('XRAY_API_ADDRESS', '127.0.0.1:62789'),
    'timeout' => max(1, (int) env('XRAY_API_TIMEOUT', 5)),
    'stats_pattern' => env('XRAY_STATS_PATTERN', 'user>>>'),
    'reset_after_read' => filter_var(env('XRAY_RESET_AFTER_READ', false), FILTER_VALIDATE_BOOL),
    'collection_enabled' => filter_var(env('XRAY_COLLECTION_ENABLED', false), FILTER_VALIDATE_BOOL),
    'user_writes_enabled' => filter_var(env('XRAY_USER_WRITES_ENABLED', false), FILTER_VALIDATE_BOOL),
    'node_id' => env('JOY_NODE_ID', gethostname() ?: 'portal-1'),
    'joy_sync_enabled' => filter_var(env('JOY_USAGE_SYNC_ENABLED', false), FILTER_VALIDATE_BOOL),
    'joy_usage_url' => env('JOY_USAGE_URL'),
    'joy_agent_id' => env('JOY_AGENT_ID', 'portal-1'),
    'joy_agent_secret' => env('JOY_AGENT_SECRET'),
    'joy_timeout' => max(1, (int) env('JOY_API_TIMEOUT', 10)),
    'joy_batch_size' => max(1, min(500, (int) env('JOY_USAGE_BATCH_SIZE', 200))),
    'session_idle_timeout' => max(60, (int) env('XRAY_SESSION_IDLE_TIMEOUT', 180)),
];
