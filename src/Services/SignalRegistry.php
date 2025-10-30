<?php

namespace SocialDept\Signal\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use SocialDept\Signal\Signals\Signal;

class SignalRegistry
{
    protected Collection $signals;

    public function __construct()
    {
        $this->signals = collect();
    }

    /**
     * Register a signal.
     */
    public function register(string $signalClass): void
    {
        if (! is_subclass_of($signalClass, Signal::class)) {
            throw new \InvalidArgumentException(
                'Signal class must extend '.Signal::class
            );
        }

        $this->signals->push($signalClass);
    }

    /**
     * Get all registered signals.
     */
    public function all(): Collection
    {
        return $this->signals->map(fn ($class) => app($class));
    }

    /**
     * Auto-discover signals in the configured directory.
     */
    public function discover(): void
    {
        if (! config('signal.auto_discovery.enabled', true)) {
            return;
        }

        $path = config('signal.auto_discovery.path', app_path('Signals'));
        $namespace = config('signal.auto_discovery.namespace', 'App\\Signals');

        if (! File::exists($path)) {
            return;
        }

        $files = File::allFiles($path);

        foreach ($files as $file) {
            $class = $namespace.'\\'.$file->getFilenameWithoutExtension();

            if (class_exists($class) && is_subclass_of($class, Signal::class)) {
                $this->register($class);
            }
        }
    }

    /**
     * Get signals that match the given event.
     */
    public function getMatchingSignals($event): Collection
    {
        return $this->all()->filter(function (Signal $signal) use ($event) {
            // Check event type
            $eventTypes = $this->normalizeValues($signal->eventTypes());
            if (! in_array($event->kind, $eventTypes)) {
                return false;
            }

            // Check collections filter (with wildcard support)
            if ($signal->collections() !== null && $event->isCommit()) {
                if (! $this->matchesCollection($event->getCollection(), $signal->collections())) {
                    return false;
                }
            }

            // Check operations filter (only for commit events)
            if ($signal->operations() !== null && $event->isCommit()) {
                $operation = $event->getOperation();
                $operations = $this->normalizeValues($signal->operations());
                if ($operation && ! in_array($operation->value, $operations)) {
                    return false;
                }
            }

            // Check DIDs filter
            if ($signal->dids() !== null) {
                if (! in_array($event->did, $signal->dids())) {
                    return false;
                }
            }

            // Check custom shouldHandle logic
            if (! $signal->shouldHandle($event)) {
                return false;
            }

            return true;
        });
    }

    /**
     * Check if a collection matches any of the patterns (supports wildcards).
     *
     * @param  string|null  $collection  The actual collection from the event
     * @param  array  $patterns  Array of collection patterns (may include wildcards like 'app.bsky.feed.*')
     */
    protected function matchesCollection(?string $collection, array $patterns): bool
    {
        if ($collection === null) {
            return false;
        }

        foreach ($patterns as $pattern) {
            // Exact match
            if ($pattern === $collection) {
                return true;
            }

            // Wildcard match
            if (str_contains($pattern, '*')) {
                // Convert wildcard pattern to regex
                // Escape special regex characters except *
                $regex = preg_quote($pattern, '/');
                // Replace escaped \* with .* for regex wildcard
                $regex = str_replace('\*', '.*', $regex);

                if (preg_match('/^'.$regex.'$/', $collection)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Normalize array values to strings (handle backed enums).
     *
     * @param  array  $values  Array of strings or backed enums
     * @return array Array of string values
     */
    protected function normalizeValues(array $values): array
    {
        return array_map(function ($value) {
            // If it's a backed enum, get its value
            if ($value instanceof \BackedEnum) {
                return $value->value;
            }

            // Otherwise, return as-is (should be a string)
            return $value;
        }, $values);
    }
}
