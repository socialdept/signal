# Jetstream vs Firehose

Signal supports two modes for consuming AT Protocol events. Understanding the differences is crucial for building efficient, scalable applications.

## Quick Comparison

| Feature          | Jetstream         | Firehose               |
|------------------|-------------------|------------------------|
| **Event Format** | Simplified JSON   | Raw CBOR/CAR           |
| **Filtering**    | Server-side       | Client-side            |
| **Bandwidth**    | Lower             | Higher                 |
| **Processing**   | Lighter           | Heavier                |
| **Best For**     | Most applications | Comprehensive indexing |

## Jetstream Mode

Jetstream is a **simplified, JSON-based event stream** built on top of the AT Protocol Firehose.
    
### When to Use Jetstream

Choose Jetstream if you're:

- Building production applications where efficiency matters
- Concerned about bandwidth and server costs
- Processing high volumes of events
- Want server-side filtering for reduced bandwidth

### Configuration

Set Jetstream as your mode in `.env`:

```env
SIGNAL_MODE=jetstream
SIGNAL_JETSTREAM_URL=wss://jetstream2.us-east.bsky.network
```

### Available Endpoints

Jetstream has multiple regional endpoints:

**US East (Default):**
```env
SIGNAL_JETSTREAM_URL=wss://jetstream2.us-east.bsky.network
```

**US West:**
```env
SIGNAL_JETSTREAM_URL=wss://jetstream1.us-west.bsky.network
```

Choose the endpoint closest to your server for best performance.

### Advantages

**1. Simplified JSON Format**

Events arrive as clean JSON objects:

```json
{
  "did": "did:plc:z72i7hdynmk6r22z27h6tvur",
  "time_us": 1234567890,
  "kind": "commit",
  "commit": {
    "rev": "abc123",
    "operation": "create",
    "collection": "app.bsky.feed.post",
    "rkey": "3k2yihcrr2c2a",
    "record": {
      "text": "Hello World!",
      "createdAt": "2024-01-15T10:30:00Z"
    }
  }
}
```

No complex parsing or decoding required.

**2. Server-Side Filtering**

Your collection filters are sent to Jetstream:

```php
public function collections(): ?array
{
    return ['app.bsky.feed.post', 'app.bsky.feed.like'];
}
```

Jetstream only sends matching events, dramatically reducing bandwidth.

**3. Lower Bandwidth**

Only receive the events you care about:

- **Jetstream**: Receive ~1,000 events/sec for specific collections
- **Firehose**: Receive ~50,000 events/sec for everything

**4. Lower Processing Overhead**

JSON parsing is faster than CBOR/CAR decoding:

- **Jetstream**: Simple JSON deserialization
- **Firehose**: Complex CBOR/CAR decoding with `revolution/laravel-bluesky`

### Limitations

**1. Client-Side Wildcards**

Wildcard patterns work client-side only:

```php
public function collections(): ?array
{
    return ['app.bsky.feed.*']; // Still receives all collections
}
```

The wildcard matching happens in your app, not on the server.

### Example Configuration

```php
// config/signal.php
return [
    'mode' => env('SIGNAL_MODE', 'jetstream'),

    'jetstream' => [
        'websocket_url' => env(
            'SIGNAL_JETSTREAM_URL',
            'wss://jetstream2.us-east.bsky.network'
        ),
    ],
];
```

## Firehose Mode

Firehose is the **raw AT Protocol event stream** with comprehensive support for all collections.

### When to Use Firehose

Choose Firehose if you're:

- Building comprehensive indexing systems
- Developing AT Protocol infrastructure
- Need access to raw CBOR/CAR data
- Prefer client-side filtering control

### Configuration

Set Firehose as your mode in `.env`:

```env
SIGNAL_MODE=firehose
SIGNAL_FIREHOSE_HOST=bsky.network
```

### Advantages

**1. Raw Event Access**

Full access to raw AT Protocol data:

```php
public function handle(SignalEvent $event): void
{
    // Access raw CBOR/CAR data
    $cid = $event->commit->cid;
    $blocks = $event->commit->blocks;
}
```

**2. Comprehensive Events**

Every event from the AT Protocol network arrives:

- All collections (standard and custom)
- All operations (create, update, delete)
- All metadata and context
- Complete repository commits

**3. Complete Control**

Full access to raw AT Protocol data:

- CID (Content Identifiers)
- Block structures
- CAR file data
- Complete repository commits

### Trade-offs

**1. Client-Side Filtering**

All filtering happens in your application:

```php
public function collections(): ?array
{
    return ['app.bsky.feed.post']; // Still receives all events
}
```

Your app receives everything and filters locally.

**2. Higher Bandwidth**

Receive the full event stream:

- **~50,000+ events per second** during peak times
- **~10-50 MB/s** of data throughput
- Requires adequate network capacity

**3. More Processing Overhead**

Complex CBOR/CAR decoding:

```php
// Signal automatically handles decoding using revolution/laravel-bluesky
$record = $event->getRecord(); // Decoded from CBOR/CAR
```

Processing is more CPU-intensive than Jetstream's JSON.

**4. Requires revolution/laravel-bluesky**

Firehose mode depends on the `revolution/laravel-bluesky` package for decoding:

```bash
composer require revolution/bluesky
```

Signal handles this dependency automatically.

### Example Configuration

```php
// config/signal.php
return [
    'mode' => env('SIGNAL_MODE', 'jetstream'),

    'firehose' => [
        'host' => env('SIGNAL_FIREHOSE_HOST', 'bsky.network'),
    ],
];
```

The WebSocket URL is constructed as:
```
wss://{host}/xrpc/com.atproto.sync.subscribeRepos
```

## Choosing the Right Mode

### Decision Tree

```
Do you need raw CBOR/CAR access?
├─ Yes → Use Firehose
└─ No
    │
    Do you want server-side filtering?
    ├─ Yes → Use Jetstream (recommended)
    └─ No → Use Firehose
```

### Use Case Examples

**Social Media Analytics (Jetstream)**

```php
// Efficient monitoring with server-side filtering
public function collections(): ?array
{
    return [
        'app.bsky.feed.post',
        'app.bsky.feed.like',
        'app.bsky.graph.follow',
    ];
}
```

**Content Moderation (Jetstream)**

```php
// Standard content monitoring
public function collections(): ?array
{
    return ['app.bsky.feed.*'];
}
```

**Comprehensive Indexer (Firehose)**

```php
// Index everything with raw data access
public function collections(): ?array
{
    return null; // All collections
}
```

## Switching Between Modes

You can switch modes without code changes:

### Option 1: Environment Variable

```env
# Development - comprehensive testing
SIGNAL_MODE=firehose

# Production - efficient processing
SIGNAL_MODE=jetstream
```

### Option 2: Runtime Configuration

```php
use SocialDept\Signals\Facades\Signal;

// Set mode dynamically
config(['signal.mode' => 'jetstream']);

Signal::start();
```

## Performance Comparison

### Bandwidth Usage

**Processing 1 hour of posts:**

| Mode      | Data Received     | Bandwidth |
|-----------|-------------------|-----------|
| Jetstream | ~50,000 events    | ~10 MB    |
| Firehose  | ~5,000,000 events | ~500 MB   |

**Savings:** 50x reduction with Jetstream

### CPU Usage

**Processing same events:**

| Mode      | CPU Usage | Processing Time |
|-----------|-----------|-----------------|
| Jetstream | ~5%       | 0.1ms per event |
| Firehose  | ~20%      | 0.4ms per event |

**Savings:** 4x more efficient with Jetstream

### Cost Implications

For a medium-traffic application:

| Mode      | Monthly Bandwidth | Est. Cost* |
|-----------|-------------------|------------|
| Jetstream | ~20 GB            | ~$2        |
| Firehose  | ~10 TB            | ~$1000     |

*Estimates vary by provider and usage

## Best Practices

### Start with Jetstream

Start with Jetstream for most applications:

```env
SIGNAL_MODE=jetstream
```

Switch to Firehose only if you need raw CBOR/CAR access.

### Use Firehose for Development

Test with Firehose in development to see all events:

```env
# .env.local
SIGNAL_MODE=firehose

# .env.production
SIGNAL_MODE=jetstream
```

### Monitor Performance

Track your Signal's performance:

```php
public function handle(SignalEvent $event): void
{
    $start = microtime(true);

    // Your logic

    $duration = microtime(true) - $start;

    if ($duration > 0.1) {
        Log::warning('Slow signal processing', [
            'signal' => static::class,
            'duration' => $duration,
            'mode' => config('signal.mode'),
        ]);
    }
}
```

### Use Queues with Firehose

Firehose generates high volume. Use queues to avoid blocking:

```php
public function shouldQueue(): bool
{
    // Queue when using Firehose
    return config('signal.mode') === 'firehose';
}
```

[Learn more about queue integration →](queues.md)

## Testing Both Modes

Test your Signals work in both modes:

```bash
# Test with Jetstream
SIGNAL_MODE=jetstream php artisan signal:test MySignal

# Test with Firehose
SIGNAL_MODE=firehose php artisan signal:test MySignal
```

[Learn more about testing →](testing.md)

## Common Questions

### Can I use both modes simultaneously?

No, each consumer runs in one mode. However, you can run multiple consumers:

```bash
# Terminal 1 - Jetstream consumer
SIGNAL_MODE=jetstream php artisan signal:consume

# Terminal 2 - Firehose consumer
SIGNAL_MODE=firehose php artisan signal:consume
```

### Will my Signals break if I switch modes?

Signals work in both modes without changes. The main difference is:
- Jetstream provides server-side filtering (more efficient)
- Firehose provides raw CBOR/CAR data access (more comprehensive)

### How do I know which mode I'm using?

Check at runtime:

```php
$mode = config('signal.mode'); // 'jetstream' or 'firehose'
```

Or via Facade:

```php
use SocialDept\Signals\Facades\Signal;

$mode = Signal::getMode();
```

### Can I switch modes while consuming?

No, you must restart the consumer:

```bash
# Stop current consumer (Ctrl+C)

# Change mode
# Edit .env: SIGNAL_MODE=firehose

# Start new consumer
php artisan signal:consume
```

## Next Steps

- **[Learn about queue integration →](queues.md)** - Handle high-volume events efficiently
- **[Review configuration options →](configuration.md)** - Fine-tune your setup
- **[See real-world examples →](examples.md)** - Learn from production patterns
