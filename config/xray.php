<?php

return [
    'binary' => env('XRAY_BINARY', '/usr/local/x-ui/bin/xray-linux-amd64'),
    'api_address' => env('XRAY_API_ADDRESS', '127.0.0.1:10085'),
    'timeout' => max(1, (int) env('XRAY_API_TIMEOUT', 5)),
    'stats_pattern' => env('XRAY_STATS_PATTERN', 'user>>>'),
    'reset_after_read' => filter_var(env('XRAY_RESET_AFTER_READ', false), FILTER_VALIDATE_BOOL),
    'collection_enabled' => filter_var(env('XRAY_COLLECTION_ENABLED', false), FILTER_VALIDATE_BOOL),
    'user_writes_enabled' => filter_var(env('XRAY_USER_WRITES_ENABLED', false), FILTER_VALIDATE_BOOL),
];
