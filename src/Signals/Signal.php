<?php

namespace SocialDept\Signal\Signals;

use SocialDept\Signal\Events\JetstreamEvent;

abstract class Signal
{
    /**
     * Define which event types to listen for.
     *
     * @return array<string> ['commit', 'identity', 'account']
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
    abstract public function handle(JetstreamEvent $event): void;

    /**
     * Determine if this signal should handle the event.
     */
    public function shouldHandle(JetstreamEvent $event): bool
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
     *
     * @return array
     */
    public function middleware(): array
    {
        return [];
    }

    /**
     * Handle a failed signal execution.
     */
    public function failed(JetstreamEvent $event, \Throwable $exception): void
    {
        //
    }
}
