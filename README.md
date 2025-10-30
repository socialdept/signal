# Signal

**Laravel package for building Signals that respond to AT Protocol events**

Signal provides a clean, Laravel-style interface for consuming real-time events from the AT Protocol. Supports both **Jetstream** (simplified JSON events) and **Firehose** (raw CBOR/CAR format) for maximum flexibility. Build reactive applications, AppViews, and custom indexers that respond to posts, likes, follows, and other social interactions on the AT Protocol network.

---

## Features

- 🔄 **Dual-Mode Support** - Choose between Jetstream (JSON) or Firehose (CBOR/CAR) based on your needs
- 🔌 **WebSocket Connection** - Connect to AT Protocol with automatic reconnection and exponential backoff
- 🎯 **Signal-based Architecture** - Clean, testable event handlers (avoiding Laravel's "listener" naming collision)
- ⭐ **Wildcard Collection Filtering** - Match multiple collections with patterns like `app.bsky.feed.*`
- 🏗️ **AppView Ready** - Full support for custom collections and building AT Protocol AppViews
- 💾 **Cursor Management** - Resume from last position after disconnections (Database, Redis, or File storage)
- ⚡ **Queue Integration** - Process events asynchronously with Laravel queues
- 🔍 **Auto-Discovery** - Automatically find and register Signals in `app/Signals`
- 🧪 **Testing Tools** - Test your Signals with sample data
- 🛠️ **Artisan Commands** - Full CLI support for managing and testing Signals

---

## Table of Contents

<!-- TOC -->
* [Installation](#installation)
* [Quick Start](#quick-start)
* [Jetstream vs Firehose](#jetstream-vs-firehose)
* [Creating Signals](#creating-signals)
* [Filtering Events](#filtering-events)
* [Queue Integration](#queue-integration)
* [Configuration](#configuration-1)
* [Programmatic Usage](#programmatic-usage)
* [Available Commands](#available-commands)
* [Testing](#testing)
* [External Resources](#external-resources)
* [Examples](#examples)
* [Requirements](#requirements)
* [License](#license)
* [Support](#support)
<!-- TOC -->

---

## Installation

Install the package via Composer:

```bash
composer require socialdept/signal
```

Run the installation command:

```bash
php artisan signal:install
```

This will:
- Publish the configuration file to `config/signal.php`
- Publish the database migration
- Run migrations (with confirmation)
- Display next steps

### Manual Installation

If you prefer manual installation:

```bash
php artisan vendor:publish --tag=signal-config
php artisan vendor:publish --tag=signal-migrations
php artisan migrate
```

---

## Quick Start

### 1. Create Your First Signal

```bash
php artisan make:signal NewPostSignal
```

This creates `app/Signals/NewPostSignal.php`:

```php
<?php

namespace App\Signals;

use SocialDept\Signal\Events\SignalEvent;
use SocialDept\Signal\Signals\Signal;

class NewPostSignal extends Signal
{
    public function eventTypes(): array
    {
        return ['commit'];
    }

    public function collections(): ?array
    {
        return ['app.bsky.feed.post'];
    }

    public function handle(SignalEvent $event): void
    {
        $record = $event->getRecord();

        logger()->info('New post created', [
            'did' => $event->did,
            'text' => $record->text ?? null,
        ]);
    }
}
```

### 2. Start Consuming Events

```bash
php artisan signal:consume
```

Your Signal will now respond to new posts on the AT Protocol network in real-time!

---

## Jetstream vs Firehose

Signal supports two modes for consuming AT Protocol events. Choose based on your use case:

### Jetstream Mode (Default)

**Best for**: Standard Bluesky collections, production efficiency, lower bandwidth

```bash
php artisan signal:consume --mode=jetstream
```

**Characteristics:**
- ✅ Simplified JSON events (easy to work with)
- ✅ Server-side collection filtering (efficient)
- ✅ Lower bandwidth and processing overhead
- ⚠️ Only standard `app.bsky.*` collections get create/update operations
- ⚠️ Custom collections only receive delete operations

**Jetstream URL options:**
- US East: `wss://jetstream2.us-east.bsky.network` (default)
- US West: `wss://jetstream1.us-west.bsky.network`

### Firehose Mode

**Best for**: Custom collections, AppViews, comprehensive indexing

```bash
php artisan signal:consume --mode=firehose
```

**Characteristics:**
- ✅ **All operations** (create, update, delete) for **all collections**
- ✅ Perfect for custom collections (e.g., `app.yourapp.*.collection`)
- ✅ Full CBOR/CAR decoding with package `revolution/laravel-bluesky`
- ⚠️ Client-side filtering only (higher bandwidth)
- ⚠️ More processing overhead

**When to use Firehose:**
- Building an AT Protocol AppView
- Working with custom collections
- Need create/update events for non-standard collections
- Building comprehensive indexes

### Configuration

Set your preferred mode in `.env`:

```env
# Use Jetstream (default)
SIGNAL_MODE=jetstream

# Or use Firehose for custom collections
SIGNAL_MODE=firehose
```

### Example: Custom Collections

If you're tracking custom collections like `app.offprint.beta.publication`, you **must** use Firehose mode:

```php
class PublicationSignal extends Signal
{
    public function collections(): ?array
    {
        return ['app.offprint.beta.publication'];
    }

    public function handle(SignalEvent $event): void
    {
        // With Jetstream: Only sees deletes ❌
        // With Firehose: Sees creates, updates, deletes ✅
    }
}
```

---

## Creating Signals

### Basic Signal Structure

Every Signal extends the base `Signal` class and must implement:

```php
use SocialDept\Signal\Enums\SignalEventType;
use SocialDept\Signal\Events\SignalEvent;
use SocialDept\Signal\Signals\Signal;

class MySignal extends Signal
{
    // Required: Define which event types to listen for
    public function eventTypes(): array
    {
        return [SignalEventType::Commit];

        // Or use strings:
        // return ['commit'];
    }

    // Required: Handle the event
    public function handle(SignalEvent $event): void
    {
        // Your logic here
    }
}
```

**Enums vs Strings**: Signal supports both typed enums and strings for better IDE support and type safety. Use whichever you prefer!

### Event Types

Three event types are available:

| Enum                        | String       | Description                                      | Use Cases                             |
|-----------------------------|--------------|--------------------------------------------------|---------------------------------------|
| `SignalEventType::Commit`   | `'commit'`   | Repository commits (posts, likes, follows, etc.) | Content creation, social interactions |
| `SignalEventType::Identity` | `'identity'` | Identity changes (handle updates)                | User profile tracking                 |
| `SignalEventType::Account`  | `'account'`  | Account status changes                           | Account monitoring                    |

### Accessing Event Data

```php
use SocialDept\Signal\Enums\SignalCommitOperation;

public function handle(SignalEvent $event): void
{
    // Common properties
    $did = $event->did;           // User's DID
    $kind = $event->kind;         // Event type
    $timestamp = $event->timeUs;  // Microsecond timestamp

    // Commit events
    if ($event->isCommit()) {
        $collection = $event->getCollection();  // e.g., 'app.bsky.feed.post'
        $operation = $event->getOperation();    // SignalCommitOperation enum
        $record = $event->getRecord();          // The actual record data
        $rkey = $event->commit->rkey;           // Record key

        // Use enum for type-safe comparisons
        if ($operation === SignalCommitOperation::Create) {
            // Handle new records
        }

        // Or get string value
        $operationString = $operation->value; // 'create', 'update', or 'delete'
    }

    // Identity events
    if ($event->isIdentity()) {
        $handle = $event->identity->handle;
    }

    // Account events
    if ($event->isAccount()) {
        $active = $event->account->active;
        $status = $event->account->status;
    }
}
```

---

## Filtering Events

### Collection Filtering (with Wildcards!)

Filter events by AT Protocol collection.

**Important**:
- **Jetstream mode**: Exact collection names are sent as URL parameters for server-side filtering. Wildcards work for client-side filtering only.
- **Firehose mode**: All filtering is client-side. Wildcards work normally.

```php
// Exact match - only posts
public function collections(): ?array
{
    return ['app.bsky.feed.post'];
}

// Wildcard - all feed events
public function collections(): ?array
{
    return ['app.bsky.feed.*'];
}

// Multiple patterns
public function collections(): ?array
{
    return [
        'app.bsky.feed.post',
        'app.bsky.feed.repost',
        'app.bsky.graph.*',  // All graph collections
    ];
}

// No filter - all collections
public function collections(): ?array
{
    return null;
}
```

### Common Collection Patterns

| Pattern            | Matches                     |
|--------------------|-----------------------------|
| `app.bsky.feed.*`  | Posts, likes, reposts, etc. |
| `app.bsky.graph.*` | Follows, blocks, mutes      |
| `app.bsky.actor.*` | Profile updates             |
| `app.bsky.*`       | All Bluesky collections     |

### Operation Filtering

Filter events by operation type (only applies to `commit` events):

```php
use SocialDept\Signal\Enums\SignalCommitOperation;

// Only handle creates (using enum)
public function operations(): ?array
{
    return [SignalCommitOperation::Create];
}

// Only handle creates and updates (using enums)
public function operations(): ?array
{
    return [
        SignalCommitOperation::Create,
        SignalCommitOperation::Update,
    ];
}

// Only handle deletes (using string)
public function operations(): ?array
{
    return ['delete'];
}

// No filter - all operations (default)
public function operations(): ?array
{
    return null;
}
```

**Available operations:**

| Enum                            | String     | Description               |
|---------------------------------|------------|---------------------------|
| `SignalCommitOperation::Create` | `'create'` | New records created       |
| `SignalCommitOperation::Update` | `'update'` | Existing records modified |
| `SignalCommitOperation::Delete` | `'delete'` | Records removed           |

**Example use cases:**
```php
use SocialDept\Signal\Enums\SignalCommitOperation;

// Signal that only handles new posts (not edits)
class NewPostSignal extends Signal
{
    public function collections(): ?array
    {
        return ['app.bsky.feed.post'];
    }

    public function operations(): ?array
    {
        return [SignalCommitOperation::Create];
    }
}

// Signal that only handles content updates
class ContentUpdateSignal extends Signal
{
    public function collections(): ?array
    {
        return ['app.bsky.feed.post'];
    }

    public function operations(): ?array
    {
        return [SignalCommitOperation::Update];
    }
}

// Signal that handles deletions for cleanup
class CleanupSignal extends Signal
{
    public function collections(): ?array
    {
        return ['app.bsky.feed.*'];
    }

    public function operations(): ?array
    {
        return [SignalCommitOperation::Delete];
    }
}
```

### DID Filtering

Filter events by specific users:

```php
public function dids(): ?array
{
    return [
        'did:plc:z72i7hdynmk6r22z27h6tvur',  // Specific user
        'did:plc:ragtjsm2j2vknwkz3zp4oxrd',  // Another user
    ];
}
```

### Custom Filtering

Add complex filtering logic:

```php
public function shouldHandle(SignalEvent $event): bool
{
    // Only handle posts with images
    if ($event->isCommit() && $event->commit->collection === 'app.bsky.feed.post') {
        $record = $event->getRecord();
        return isset($record->embed);
    }

    return true;
}
```

---

## Queue Integration

Process events asynchronously using Laravel queues:

```php
class HeavyProcessingSignal extends Signal
{
    public function eventTypes(): array
    {
        return ['commit'];
    }

    // Enable queueing
    public function shouldQueue(): bool
    {
        return true;
    }

    // Optional: Customize queue
    public function queue(): string
    {
        return 'high-priority';
    }

    // Optional: Customize connection
    public function queueConnection(): string
    {
        return 'redis';
    }

    public function handle(SignalEvent $event): void
    {
        // This runs in a queue job
        $this->performExpensiveOperation($event);
    }

    // Handle failures
    public function failed(SignalEvent $event, \Throwable $exception): void
    {
        Log::error('Signal failed', [
            'event' => $event->toArray(),
            'error' => $exception->getMessage(),
        ]);
    }
}
```

---

## Configuration

Configuration is stored in `config/signal.php`:

### Consumer Mode

Choose between Jetstream (JSON) or Firehose (CBOR) mode:

```php
'mode' => env('SIGNAL_MODE', 'jetstream'),
```

Options:
- `jetstream` - JSON events, server-side filtering (default)
- `firehose` - CBOR events, client-side filtering (required for custom collections)

### Jetstream Configuration

```php
'websocket_url' => env('SIGNAL_JETSTREAM_URL', 'wss://jetstream2.us-east.bsky.network'),
```

Available endpoints:
- **US East**: `wss://jetstream2.us-east.bsky.network` (default)
- **US West**: `wss://jetstream1.us-west.bsky.network`

### Firehose Configuration

```php
'firehose' => [
    'host' => env('SIGNAL_FIREHOSE_HOST', 'bsky.network'),
],
```

The raw firehose endpoint is: `wss://{host}/xrpc/com.atproto.sync.subscribeRepos`

### Cursor Storage

Choose how to store cursor positions:

```php
'cursor_storage' => env('SIGNAL_CURSOR_STORAGE', 'database'),
```

| Driver     | Best For                      | Configuration      |
|------------|-------------------------------|--------------------|
| `database` | Production, multi-server      | Default connection |
| `redis`    | High performance, distributed | Redis connection   |
| `file`     | Development, single server    | Storage path       |

### Environment Variables

Add to your `.env`:

```env
# Consumer Mode
SIGNAL_MODE=jetstream  # or 'firehose' for custom collections

# Jetstream Configuration
SIGNAL_JETSTREAM_URL=wss://jetstream2.us-east.bsky.network

# Firehose Configuration (only needed if using firehose mode)
SIGNAL_FIREHOSE_HOST=bsky.network

# Optional Configuration
SIGNAL_CURSOR_STORAGE=database
SIGNAL_QUEUE_CONNECTION=redis
SIGNAL_QUEUE=signal
SIGNAL_BATCH_SIZE=100
SIGNAL_RATE_LIMIT=1000
```

### Auto-Discovery

Signals are automatically discovered from `app/Signals`. Disable if needed:

```php
'auto_discovery' => [
    'enabled' => true,
    'path' => app_path('Signals'),
    'namespace' => 'App\\Signals',
],
```

Or manually register Signals:

```php
'signals' => [
    \App\Signals\NewPostSignal::class,
    \App\Signals\NewFollowSignal::class,
],
```

---

## Programmatic Usage

You can start and stop the consumer programmatically using the `Signal` facade:

```php
use SocialDept\Signal\Facades\Signal;

// Start consuming events (uses mode from config)
Signal::start();

// Start from a specific cursor
Signal::start(cursor: 123456789);

// Check which mode is active
$mode = Signal::getMode(); // Returns 'jetstream' or 'firehose'

// Stop consuming events
Signal::stop();
```

The facade automatically resolves the correct consumer (Jetstream or Firehose) based on your `config('signal.mode')` setting. This allows you to:

- Switch between modes by changing configuration
- Start consumers from application code (e.g., in a custom command)
- Integrate Signal into existing application workflows

```php
// Example: Start consumer based on environment
if (app()->environment('production')) {
    config(['signal.mode' => 'jetstream']); // Use efficient Jetstream
} else {
    config(['signal.mode' => 'firehose']); // Use comprehensive Firehose for testing
}

Signal::start();
```

---

## Available Commands

### `signal:install`
Install the package (publish config, migrations, run migrations)

```bash
php artisan signal:install
```

### `signal:consume`
Start consuming events from AT Protocol

```bash
# Use default mode from config
php artisan signal:consume

# Override mode
php artisan signal:consume --mode=jetstream
php artisan signal:consume --mode=firehose

# Start from specific cursor
php artisan signal:consume --cursor=123456789

# Start fresh (ignore stored cursor)
php artisan signal:consume --fresh

# Combine options
php artisan signal:consume --mode=firehose --fresh
```

### `signal:list`
List all registered Signals

```bash
php artisan signal:list
```

### `signal:make`
Create a new Signal class

```bash
php artisan make:signal NewPostSignal

# With options
php artisan make:signal FollowSignal --type=commit --collection=app.bsky.graph.follow
```

### `signal:test`
Test a Signal with sample data

```bash
php artisan signal:test NewPostSignal
```

---

## Testing

Signal includes a comprehensive test suite. Test your Signals:

### Unit Testing

```php
use SocialDept\Signal\Events\CommitEvent;
use SocialDept\Signal\Events\SignalEvent;

class NewPostSignalTest extends TestCase
{
    /** @test */
    public function it_handles_new_posts()
    {
        $signal = new NewPostSignal();

        $event = new SignalEvent(
            did: 'did:plc:test',
            timeUs: time() * 1000000,
            kind: 'commit',
            commit: new CommitEvent(
                rev: 'test',
                operation: 'create',
                collection: 'app.bsky.feed.post',
                rkey: 'test',
                record: (object) [
                    'text' => 'Hello World!',
                    'createdAt' => now()->toIso8601String(),
                ],
            ),
        );

        $signal->handle($event);

        // Assert your expected behavior
    }
}
```

### Testing with Artisan

```bash
php artisan signal:test NewPostSignal
```

---

## External Resources

- [AT Protocol Documentation](https://atproto.com/)
- [Firehose Documentation](https://docs.bsky.app/docs/advanced-guides/firehose)
- [Bluesky Lexicon](https://atproto.com/lexicons)

---

## Examples

### Monitor All Feed Activity

```php
class FeedMonitorSignal extends Signal
{
    public function eventTypes(): array
    {
        return ['commit'];
    }

    public function collections(): ?array
    {
        return ['app.bsky.feed.*'];
    }

    public function handle(SignalEvent $event): void
    {
        // Handles posts, likes, reposts, etc.
        Log::info('Feed activity', [
            'collection' => $event->getCollection(),
            'operation' => $event->getOperation(),
            'did' => $event->did,
        ]);
    }
}
```

### Track New Follows

```php
class NewFollowSignal extends Signal
{
    public function eventTypes(): array
    {
        return ['commit'];
    }

    public function collections(): ?array
    {
        return ['app.bsky.graph.follow'];
    }

    public function handle(SignalEvent $event): void
    {
        if ($event->commit->isCreate()) {
            $record = $event->getRecord();

            // Store follow relationship
            Follow::create([
                'follower_did' => $event->did,
                'following_did' => $record->subject,
            ]);
        }
    }
}
```

### Content Moderation

```php
class ModerationSignal extends Signal
{
    public function eventTypes(): array
    {
        return ['commit'];
    }

    public function collections(): ?array
    {
        return ['app.bsky.feed.post'];
    }

    public function shouldQueue(): bool
    {
        return true;
    }

    public function handle(SignalEvent $event): void
    {
        $record = $event->getRecord();

        if ($this->containsProhibitedContent($record->text)) {
            $this->flagForModeration($event->did, $record);
        }
    }
}
```

---

## Requirements

- PHP 8.2 or higher
- Laravel 11.0 or higher
- WebSocket support (enabled by default in most environments)

---

## License

The MIT License (MIT). Please see [LICENSE](LICENSE) for more information.

---

## Support

For issues, questions, or feature requests:
- Read the [README.md](./README.md) before opening issues
- Search through existing issues
- Open new issue

---

**Built for the AT Protocol ecosystem** • Made with ❤️ by Social Dept
