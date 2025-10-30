<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Signal Mode
    |--------------------------------------------------------------------------
    |
    | The mode determines which AT Protocol stream to consume:
    | - 'jetstream': JSON events with server-side collection filtering
    |                (only standard app.bsky.* collections get create/update)
    | - 'firehose':  Raw CBOR events with client-side filtering
    |                (all collections including custom ones get all operations)
    |
    */
    'mode' => env('SIGNAL_MODE', 'jetstream'),

    /*
    |--------------------------------------------------------------------------
    | Jetstream WebSocket URL
    |--------------------------------------------------------------------------
    |
    | The WebSocket URL for the AT Protocol Jetstream service.
    | US East: wss://jetstream2.us-east.bsky.network
    | US West: wss://jetstream1.us-west.bsky.network
    |
    */
    'websocket_url' => env('SIGNAL_JETSTREAM_URL', 'wss://jetstream2.us-east.bsky.network'),

    /*
    |--------------------------------------------------------------------------
    | Firehose Configuration
    |--------------------------------------------------------------------------
    |
    | Raw AT Protocol firehose settings.
    | Note: Firehose does NOT support server-side collection filtering.
    |
    */
    'firehose' => [
        'host' => env('SIGNAL_FIREHOSE_HOST', 'bsky.network'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Cursor Storage Driver
    |--------------------------------------------------------------------------
    |
    | Determines how Signal stores the cursor position for resuming after
    | disconnections. Options: 'database', 'redis', 'file'
    |
    */
    'cursor_storage' => env('SIGNAL_CURSOR_STORAGE', 'database'),

    /*
    |--------------------------------------------------------------------------
    | Cursor Storage Configuration
    |--------------------------------------------------------------------------
    */
    'cursor_config' => [
        'database' => [
            'table' => 'signal_cursors',
            'connection' => null, // null = default connection
        ],
        'redis' => [
            'connection' => 'default',
            'key' => 'signal:cursor',
        ],
        'file' => [
            'path' => storage_path('signal/cursor.json'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Signals
    |--------------------------------------------------------------------------
    |
    | Register your Signals here, or use auto-discovery by placing them
    | in app/Signals directory.
    |
    */
    'signals' => [
        // App\Signals\PostCreateSignal::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Auto-Discovery
    |--------------------------------------------------------------------------
    |
    | Automatically discover Signals in the specified directory.
    |
    */
    'auto_discovery' => [
        'enabled' => true,
        'path' => app_path('Signals'),
        'namespace' => 'App\\Signals',
    ],

    /*
    |--------------------------------------------------------------------------
    | Queue Configuration
    |--------------------------------------------------------------------------
    |
    | Default queue settings for Signals that should be queued.
    |
    */
    'queue' => [
        'connection' => env('SIGNAL_QUEUE_CONNECTION', 'default'),
        'queue' => env('SIGNAL_QUEUE', 'signal'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Connection Settings
    |--------------------------------------------------------------------------
    */
    'connection' => [
        'reconnect_attempts' => 5,
        'reconnect_delay' => 5, // Base delay in seconds (exponential backoff)
        'max_reconnect_delay' => 60, // Maximum delay in seconds
        'timeout' => 60, // seconds
    ],

    /*
    |--------------------------------------------------------------------------
    | Performance & Rate Limiting
    |--------------------------------------------------------------------------
    */
    'performance' => [
        'batch_size' => env('SIGNAL_BATCH_SIZE', 100),
        'rate_limit' => env('SIGNAL_RATE_LIMIT', 1000), // events per minute
    ],

    /*
    |--------------------------------------------------------------------------
    | Logging
    |--------------------------------------------------------------------------
    */
    'logging' => [
        'channel' => env('SIGNAL_LOG_CHANNEL', 'stack'),
        'level' => env('SIGNAL_LOG_LEVEL', 'info'),
    ],
];
