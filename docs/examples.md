# Real-World Examples

Learn from production-ready Signal examples covering common use cases.

## Social Media Analytics

Track engagement metrics across Bluesky.

```php
<?php

namespace App\Signals;

use App\Models\EngagementMetric;
use SocialDept\Signal\Enums\SignalCommitOperation;
use SocialDept\Signal\Events\SignalEvent;
use SocialDept\Signal\Signals\Signal;
use Illuminate\Support\Facades\DB;

class EngagementTrackerSignal extends Signal
{
    public function eventTypes(): array
    {
        return ['commit'];
    }

    public function collections(): ?array
    {
        return [
            'app.bsky.feed.post',
            'app.bsky.feed.like',
            'app.bsky.feed.repost',
            'app.bsky.graph.follow',
        ];
    }

    public function operations(): ?array
    {
        return [SignalCommitOperation::Create];
    }

    public function shouldQueue(): bool
    {
        return true;
    }

    public function handle(SignalEvent $event): void
    {
        $collection = $event->getCollection();
        $timestamp = $event->getTimestamp();

        // Increment counter for this hour
        DB::table('engagement_metrics')
            ->updateOrInsert(
                [
                    'collection' => $collection,
                    'hour' => $timestamp->startOfHour(),
                ],
                [
                    'count' => DB::raw('count + 1'),
                    'updated_at' => now(),
                ]
            );
    }
}
```

**Use case:** Build analytics dashboards showing posts/hour, likes/hour, follows/hour.

## Content Moderation

Automatically flag problematic content.

```php
<?php

namespace App\Signals;

use App\Models\FlaggedPost;
use App\Services\ModerationService;
use SocialDept\Signal\Enums\SignalCommitOperation;
use SocialDept\Signal\Events\SignalEvent;
use SocialDept\Signal\Signals\Signal;

class ModerationSignal extends Signal
{
    public function __construct(
        private ModerationService $moderation
    ) {}

    public function eventTypes(): array
    {
        return ['commit'];
    }

    public function collections(): ?array
    {
        return ['app.bsky.feed.post'];
    }

    public function operations(): ?array
    {
        return [SignalCommitOperation::Create];
    }

    public function shouldQueue(): bool
    {
        return true;
    }

    public function queue(): string
    {
        return 'moderation';
    }

    public function handle(SignalEvent $event): void
    {
        $record = $event->getRecord();

        if (!isset($record->text)) {
            return;
        }

        $result = $this->moderation->analyze($record->text);

        if ($result->needsReview) {
            FlaggedPost::create([
                'did' => $event->did,
                'rkey' => $event->commit->rkey,
                'text' => $record->text,
                'reason' => $result->reason,
                'confidence' => $result->confidence,
                'flagged_at' => now(),
            ]);
        }
    }

    public function failed(SignalEvent $event, \Throwable $exception): void
    {
        Log::error('Moderation signal failed', [
            'did' => $event->did,
            'error' => $exception->getMessage(),
        ]);
    }
}
```

**Use case:** Automated content moderation with human review queue.

## User Activity Feed

Build a personalized activity feed.

```php
<?php

namespace App\Signals;

use App\Models\Activity;
use App\Models\User;
use SocialDept\Signal\Enums\SignalCommitOperation;
use SocialDept\Signal\Events\SignalEvent;
use SocialDept\Signal\Signals\Signal;

class ActivityFeedSignal extends Signal
{
    public function eventTypes(): array
    {
        return ['commit'];
    }

    public function collections(): ?array
    {
        return [
            'app.bsky.feed.post',
            'app.bsky.feed.like',
            'app.bsky.feed.repost',
        ];
    }

    public function operations(): ?array
    {
        return [SignalCommitOperation::Create];
    }

    public function shouldQueue(): bool
    {
        return true;
    }

    public function handle(SignalEvent $event): void
    {
        // Check if we're tracking this user
        $user = User::where('did', $event->did)->first();

        if (!$user) {
            return;
        }

        // Check if any followers want to see this
        $followerIds = $user->followers()->pluck('id');

        if ($followerIds->isEmpty()) {
            return;
        }

        $collection = $event->getCollection();

        // Create activity for each follower's feed
        foreach ($followerIds as $followerId) {
            Activity::create([
                'user_id' => $followerId,
                'actor_did' => $event->did,
                'type' => $this->getActivityType($collection),
                'data' => $event->toArray(),
                'created_at' => $event->getTimestamp(),
            ]);
        }
    }

    private function getActivityType(string $collection): string
    {
        return match ($collection) {
            'app.bsky.feed.post' => 'post',
            'app.bsky.feed.like' => 'like',
            'app.bsky.feed.repost' => 'repost',
            default => 'unknown',
        };
    }
}
```

**Use case:** Show users activity from people they follow.

## Real-Time Notifications

Send notifications for mentions and interactions.

```php
<?php

namespace App\Signals;

use App\Models\User;
use App\Notifications\MentionedInPost;
use SocialDept\Signal\Enums\SignalCommitOperation;
use SocialDept\Signal\Events\SignalEvent;
use SocialDept\Signal\Signals\Signal;

class MentionNotificationSignal extends Signal
{
    public function eventTypes(): array
    {
        return ['commit'];
    }

    public function collections(): ?array
    {
        return ['app.bsky.feed.post'];
    }

    public function operations(): ?array
    {
        return [SignalCommitOperation::Create];
    }

    public function shouldQueue(): bool
    {
        return true;
    }

    public function handle(SignalEvent $event): void
    {
        $record = $event->getRecord();

        if (!isset($record->facets)) {
            return;
        }

        // Extract mentions from facets
        $mentions = collect($record->facets)
            ->filter(fn($facet) => isset($facet->features))
            ->flatMap(fn($facet) => $facet->features)
            ->filter(fn($feature) => $feature->{'$type'} === 'app.bsky.richtext.facet#mention')
            ->pluck('did')
            ->unique();

        foreach ($mentions as $mentionedDid) {
            $user = User::where('did', $mentionedDid)->first();

            if ($user) {
                $user->notify(new MentionedInPost(
                    authorDid: $event->did,
                    text: $record->text ?? '',
                    uri: "at://{$event->did}/app.bsky.feed.post/{$event->commit->rkey}"
                ));
            }
        }
    }
}
```

**Use case:** Real-time notifications when users are mentioned.

## Follow Tracker

Track follow relationships and send notifications.

```php
<?php

namespace App\Signals;

use App\Models\Follow;
use App\Models\User;
use App\Notifications\NewFollower;
use SocialDept\Signal\Enums\SignalCommitOperation;
use SocialDept\Signal\Events\SignalEvent;
use SocialDept\Signal\Signals\Signal;

class FollowTrackerSignal extends Signal
{
    public function eventTypes(): array
    {
        return ['commit'];
    }

    public function collections(): ?array
    {
        return ['app.bsky.graph.follow'];
    }

    public function operations(): ?array
    {
        return [
            SignalCommitOperation::Create,
            SignalCommitOperation::Delete,
        ];
    }

    public function shouldQueue(): bool
    {
        return true;
    }

    public function handle(SignalEvent $event): void
    {
        $record = $event->getRecord();
        $operation = $event->getOperation();

        if ($operation === SignalCommitOperation::Create) {
            $this->handleNewFollow($event, $record);
        } else {
            $this->handleUnfollow($event);
        }
    }

    private function handleNewFollow(SignalEvent $event, object $record): void
    {
        Follow::create([
            'follower_did' => $event->did,
            'following_did' => $record->subject,
            'created_at' => $record->createdAt ?? now(),
        ]);

        // Notify the followed user
        $followedUser = User::where('did', $record->subject)->first();

        if ($followedUser) {
            $followedUser->notify(new NewFollower($event->did));
        }
    }

    private function handleUnfollow(SignalEvent $event): void
    {
        Follow::where('follower_did', $event->did)
            ->where('rkey', $event->commit->rkey)
            ->delete();
    }
}
```

**Use case:** Track follows and notify users of new followers.

## Search Indexer

Index posts for full-text search.

```php
<?php

namespace App\Signals;

use App\Models\Post;
use Laravel\Scout\Searchable;
use SocialDept\Signal\Enums\SignalCommitOperation;
use SocialDept\Signal\Events\SignalEvent;
use SocialDept\Signal\Signals\Signal;

class SearchIndexerSignal extends Signal
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

    public function queue(): string
    {
        return 'indexing';
    }

    public function handle(SignalEvent $event): void
    {
        $operation = $event->getOperation();

        match ($operation) {
            SignalCommitOperation::Create,
            SignalCommitOperation::Update => $this->indexPost($event),
            SignalCommitOperation::Delete => $this->deletePost($event),
        };
    }

    private function indexPost(SignalEvent $event): void
    {
        $record = $event->getRecord();

        $post = Post::updateOrCreate(
            [
                'did' => $event->did,
                'rkey' => $event->commit->rkey,
            ],
            [
                'text' => $record->text ?? '',
                'created_at' => $record->createdAt ?? now(),
                'indexed_at' => now(),
            ]
        );

        // Scout automatically indexes
        $post->searchable();
    }

    private function deletePost(SignalEvent $event): void
    {
        $post = Post::where('did', $event->did)
            ->where('rkey', $event->commit->rkey)
            ->first();

        if ($post) {
            $post->unsearchable();
            $post->delete();
        }
    }
}
```

**Use case:** Full-text search across all Bluesky posts.

## Trend Detection

Identify trending topics and hashtags.

```php
<?php

namespace App\Signals;

use App\Models\TrendingTopic;
use Illuminate\Support\Facades\Cache;
use SocialDept\Signal\Enums\SignalCommitOperation;
use SocialDept\Signal\Events\SignalEvent;
use SocialDept\Signal\Signals\Signal;

class TrendDetectionSignal extends Signal
{
    public function eventTypes(): array
    {
        return ['commit'];
    }

    public function collections(): ?array
    {
        return ['app.bsky.feed.post'];
    }

    public function operations(): ?array
    {
        return [SignalCommitOperation::Create];
    }

    public function shouldQueue(): bool
    {
        return true;
    }

    public function handle(SignalEvent $event): void
    {
        $record = $event->getRecord();

        if (!isset($record->text)) {
            return;
        }

        // Extract hashtags
        preg_match_all('/#(\w+)/', $record->text, $matches);

        foreach ($matches[1] as $hashtag) {
            $this->incrementHashtag($hashtag);
        }
    }

    private function incrementHashtag(string $hashtag): void
    {
        $key = "trending:hashtag:{$hashtag}";

        // Increment counter (expires after 1 hour)
        $count = Cache::increment($key, 1);

        if (!Cache::has($key)) {
            Cache::put($key, 1, now()->addHour());
        }

        // Update trending topics if threshold reached
        if ($count > 100) {
            TrendingTopic::updateOrCreate(
                ['hashtag' => $hashtag],
                ['count' => $count, 'updated_at' => now()]
            );
        }
    }
}
```

**Use case:** Identify trending hashtags and topics in real-time.

## Custom AppView

Index custom collections for your AppView.

```php
<?php

namespace App\Signals;

use App\Models\Publication;
use SocialDept\Signal\Enums\SignalCommitOperation;
use SocialDept\Signal\Events\SignalEvent;
use SocialDept\Signal\Signals\Signal;

class PublicationIndexerSignal extends Signal
{
    public function eventTypes(): array
    {
        return ['commit'];
    }

    public function collections(): ?array
    {
        return [
            'app.offprint.beta.publication',
            'app.offprint.beta.post',
        ];
    }

    public function shouldQueue(): bool
    {
        return true;
    }

    public function handle(SignalEvent $event): void
    {
        $collection = $event->getCollection();
        $operation = $event->getOperation();

        if ($collection === 'app.offprint.beta.publication') {
            $this->handlePublication($event, $operation);
        } else {
            $this->handlePost($event, $operation);
        }
    }

    private function handlePublication(SignalEvent $event, SignalCommitOperation $operation): void
    {
        if ($operation === SignalCommitOperation::Delete) {
            Publication::where('did', $event->did)
                ->where('rkey', $event->commit->rkey)
                ->delete();
            return;
        }

        $record = $event->getRecord();

        Publication::updateOrCreate(
            [
                'did' => $event->did,
                'rkey' => $event->commit->rkey,
            ],
            [
                'title' => $record->title ?? '',
                'description' => $record->description ?? null,
                'created_at' => $record->createdAt ?? now(),
            ]
        );
    }

    private function handlePost(SignalEvent $event, SignalCommitOperation $operation): void
    {
        // Handle custom post records
    }
}
```

**Use case:** Build AT Protocol AppViews with custom collections.

## Rate-Limited API Integration

Integrate with external APIs respecting rate limits.

```php
<?php

namespace App\Signals;

use App\Services\ExternalAPIService;
use Illuminate\Support\Facades\RateLimiter;
use SocialDept\Signal\Events\SignalEvent;
use SocialDept\Signal\Signals\Signal;

class APIIntegrationSignal extends Signal
{
    public function __construct(
        private ExternalAPIService $api
    ) {}

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

        // Rate limit: 100 calls per minute
        $executed = RateLimiter::attempt(
            'external-api',
            $perMinute = 100,
            function () use ($event, $record) {
                $this->api->sendPost([
                    'author' => $event->did,
                    'text' => $record->text ?? '',
                    'timestamp' => $event->getTimestamp(),
                ]);
            }
        );

        if (!$executed) {
            // Re-queue for later
            dispatch(fn() => $this->handle($event))
                ->delay(now()->addMinutes(1));
        }
    }
}
```

**Use case:** Mirror content to external platforms with rate limiting.

## Multi-Collection Analytics

Track engagement across multiple collection types.

```php
<?php

namespace App\Signals;

use App\Models\UserMetrics;
use SocialDept\Signal\Enums\SignalCommitOperation;
use SocialDept\Signal\Events\SignalEvent;
use SocialDept\Signal\Signals\Signal;

class UserMetricsSignal extends Signal
{
    public function eventTypes(): array
    {
        return ['commit'];
    }

    public function collections(): ?array
    {
        return ['app.bsky.feed.*', 'app.bsky.graph.*'];
    }

    public function operations(): ?array
    {
        return [SignalCommitOperation::Create];
    }

    public function shouldQueue(): bool
    {
        return true;
    }

    public function handle(SignalEvent $event): void
    {
        $collection = $event->getCollection();

        $metrics = UserMetrics::firstOrCreate(
            ['did' => $event->did],
            ['total_posts' => 0, 'total_likes' => 0, 'total_follows' => 0]
        );

        match ($collection) {
            'app.bsky.feed.post' => $metrics->increment('total_posts'),
            'app.bsky.feed.like' => $metrics->increment('total_likes'),
            'app.bsky.graph.follow' => $metrics->increment('total_follows'),
            default => null,
        };

        $metrics->touch('last_activity_at');
    }
}
```

**Use case:** User activity metrics and leaderboards.

## Performance Tips

### Batch Database Operations

```php
public function handle(SignalEvent $event): void
{
    // Bad - individual inserts
    Post::create([...]);

    // Good - batch inserts
    $posts = Cache::get('pending_posts', []);
    $posts[] = [...];

    if (count($posts) >= 100) {
        Post::insert($posts);
        Cache::forget('pending_posts');
    } else {
        Cache::put('pending_posts', $posts, now()->addMinutes(5));
    }
}
```

### Use Queues for Heavy Operations

```php
public function shouldQueue(): bool
{
    // Queue if operation takes > 100ms
    return true;
}
```

### Add Indexes for Filtering

```php
// Migration for fast lookups
Schema::table('posts', function (Blueprint $table) {
    $table->index(['did', 'rkey']);
    $table->index('created_at');
});
```

## Next Steps

- **[Review signal architecture →](signals.md)** - Understand Signal structure
- **[Learn about filtering →](filtering.md)** - Master event filtering
- **[Explore queue integration →](queues.md)** - Build high-performance Signals
- **[Configure your setup →](configuration.md)** - Optimize configuration
