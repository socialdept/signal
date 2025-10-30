# Signal

**Laravel package for building Signals that respond to AT Protocol Jetstream events**

Signal provides a clean, Laravel-style interface for consuming real-time events from the AT Protocol firehose (Jetstream). Build reactive applications that respond to posts, likes, follows, and other social interactions on the AT Protocol network.

---

## Features

- 🔌 **WebSocket Connection** - Connect to AT Protocol Jetstream with automatic reconnection
- 🎯 **Signal-based Architecture** - Clean, testable event handlers (avoiding Laravel's "listener" naming collision)
- ⭐ **Wildcard Collection Filtering** - Match multiple collections with patterns like `app.bsky.feed.*`
- 💾 **Cursor Management** - Resume from last position after disconnections (Database, Redis, or File storage)
- ⚡ **Queue Integration** - Process events asynchronously with Laravel queues
- 🔍 **Auto-Discovery** - Automatically find and register Signals in `app/Signals`
- 🧪 **Testing Tools** - Test your Signals with sample data
- 🛠️ **Artisan Commands** - Full CLI support for managing and testing Signals

---

## Table of Contents

- [Installation](#installation)
- [Quick Start](#quick-start)
- [Creating Signals](#creating-signals)
- [Filtering Events](#filtering-events)
- [Queue Integration](#queue-integration)
- [Configuration](#configuration)
- [Available Commands](#available-commands)
- [Testing](#testing)
- [Documentation](#documentation)
- [License](#license)

---

## Installation

Install the package via Composer:

```bash
composer require social-dept/signal
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

use SocialDept\Signal\Events\JetstreamEvent;
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

    public function handle(JetstreamEvent $event): void
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

## Creating Signals

### Basic Signal Structure

Every Signal extends the base `Signal` class and must implement:

```php
use SocialDept\Signal\Events\JetstreamEvent;
use SocialDept\Signal\Signals\Signal;

class MySignal extends Signal
{
    // Required: Define which event types to listen for
    public function eventTypes(): array
    {
        return ['commit']; // 'commit', 'identity', or 'account'
    }

    // Required: Handle the event
    public function handle(JetstreamEvent $event): void
    {
        // Your logic here
    }
}
```

### Event Types

Three event types are available:

| Type | Description | Use Cases |
|------|-------------|-----------|
| `commit` | Repository commits (posts, likes, follows, etc.) | Content creation, social interactions |
| `identity` | Identity changes (handle updates) | User profile tracking |
| `account` | Account status changes | Account monitoring |

### Accessing Event Data

```php
public function handle(JetstreamEvent $event): void
{
    // Common properties
    $did = $event->did;           // User's DID
    $kind = $event->kind;         // Event type
    $timestamp = $event->timeUs;  // Microsecond timestamp

    // Commit events
    if ($event->isCommit()) {
        $collection = $event->getCollection();  // e.g., 'app.bsky.feed.post'
        $operation = $event->getOperation();    // 'create', 'update', or 'delete'
        $record = $event->getRecord();          // The actual record data
        $rkey = $event->commit->rkey;           // Record key
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

Filter events by AT Protocol collection:

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

| Pattern | Matches |
|---------|---------|
| `app.bsky.feed.*` | Posts, likes, reposts, etc. |
| `app.bsky.graph.*` | Follows, blocks, mutes |
| `app.bsky.actor.*` | Profile updates |
| `app.bsky.*` | All Bluesky collections |

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
public function shouldHandle(JetstreamEvent $event): bool
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

    public function handle(JetstreamEvent $event): void
    {
        // This runs in a queue job
        $this->performExpensiveOperation($event);
    }

    // Handle failures
    public function failed(JetstreamEvent $event, \Throwable $exception): void
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

### Jetstream URL

```php
'websocket_url' => env('SIGNAL_JETSTREAM_URL', 'wss://jetstream2.us-east.bsky.network'),
```

Available endpoints:
- **US East**: `wss://jetstream2.us-east.bsky.network`
- **US West**: `wss://jetstream1.us-west.bsky.network`

### Cursor Storage

Choose how to store cursor positions:

```php
'cursor_storage' => env('SIGNAL_CURSOR_STORAGE', 'database'),
```

| Driver | Best For | Configuration |
|--------|----------|---------------|
| `database` | Production, multi-server | Default connection |
| `redis` | High performance, distributed | Redis connection |
| `file` | Development, single server | Storage path |

### Environment Variables

Add to your `.env`:

```env
# Required
SIGNAL_JETSTREAM_URL=wss://jetstream2.us-east.bsky.network

# Optional
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

## Available Commands

### `signal:install`
Install the package (publish config, migrations, run migrations)

```bash
php artisan signal:install
```

### `signal:consume`
Start consuming events from Jetstream

```bash
php artisan signal:consume

# Start from specific cursor
php artisan signal:consume --cursor=123456789

# Start fresh (ignore stored cursor)
php artisan signal:consume --fresh
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
use SocialDept\Signal\Events\JetstreamEvent;

class NewPostSignalTest extends TestCase
{
    /** @test */
    public function it_handles_new_posts()
    {
        $signal = new NewPostSignal();

        $event = new JetstreamEvent(
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

## Documentation

For detailed documentation, see:

- **[INSTALLATION.md](./INSTALLATION.md)** - Complete installation guide with troubleshooting
- **[PACKAGE_SUMMARY.md](./PACKAGE_SUMMARY.md)** - Quick reference for package components
- **[WILDCARD_EXAMPLES.md](./WILDCARD_EXAMPLES.md)** - Comprehensive wildcard pattern guide
- **[IMPLEMENTATION_PLAN.md](./IMPLEMENTATION_PLAN.md)** - Full architecture and implementation details

### External Resources

- [AT Protocol Documentation](https://atproto.com/)
- [Jetstream Documentation](https://docs.bsky.app/docs/advanced-guides/jetstream)
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

    public function handle(JetstreamEvent $event): void
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

    public function handle(JetstreamEvent $event): void
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

    public function handle(JetstreamEvent $event): void
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

## Contributing

Contributions are welcome! Please see [CONTRIBUTING.md](contributing.md) for details.

---

## Support

For issues, questions, or feature requests:
- Open an issue on GitHub
- Check the [documentation files](#documentation)
- Review the [implementation plan](./IMPLEMENTATION_PLAN.md)

---

**Built for the AT Protocol ecosystem** • Made with ❤️ by Social Dept
