<?php

namespace SocialDept\Signals\Signals;

use SocialDept\Signals\Events\SignalEvent;

abstract class Signal
{
    /**
     * Define which event types to listen for.
     *
     * @return array<string|\SocialDept\Signals\Enums\SignalEventType>
     */
    abstract public function eventTypes(): array;

    /**
     * Define collections to watch (optional, null = all).
     * Supports wildcards using asterisk (*).
     *
     * Examples:
     * - ['app.bsky.feed.post'] - Only posts
     * - ['app.bsky.feed.*'] - All feed collections (post, like, repost, etc.)
     * - ['app.bsky.graph.*'] - All graph collections (follow, block, etc.)
     * - ['app.bsky.*'] - All app.bsky collections
     *
     * @return array<string>|null
     */
    public function collections(): ?array
    {
        return null;
    }

    /**
     * Define operations to watch (optional, null = all).
     *
     * Only applies to 'commit' event types.
     *
     * Examples:
     * - [SignalCommitOperation::Create] - Only handle creates
     * - [SignalCommitOperation::Create, SignalCommitOperation::Update] - Handle creates and updates
     * - ['create', 'update'] - Handle creates and updates (string format)
     * - [SignalCommitOperation::Delete] - Only handle deletes
     * - null - Handle all operations (default)
     *
     * @return array<string|\SocialDept\Signals\Enums\SignalCommitOperation>|null
     */
    public function operations(): ?array
    {
        return null;
    }

    /**
     * Define DIDs to watch (optional, null = all).
     *
     * @return array<string>|null
     */
    public function dids(): ?array
    {
        return null;
    }

    /**
     * Handle the Jetstream event.
     */
    abstract public function handle(SignalEvent $event): void;

    /**
     * Determine if this signal should handle the event.
     */
    public function shouldHandle(SignalEvent $event): bool
    {
        return true;
    }

    /**
     * Should this signal be queued?
     */
    public function shouldQueue(): bool
    {
        return false;
    }

    /**
     * Get the queue connection name.
     */
    public function queueConnection(): ?string
    {
        return config('signal.queue.connection');
    }

    /**
     * Get the queue name.
     */
    public function queue(): ?string
    {
        return config('signal.queue.queue');
    }

    /**
     * Middleware to run before handling the event.
     */
    public function middleware(): array
    {
        return [];
    }

    /**
     * Handle a failed signal execution.
     */
    public function failed(SignalEvent $event, \Throwable $exception): void
    {
        //
    }
}
