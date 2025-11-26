<?php

namespace SocialDept\AtpSignals\Services;

use InvalidArgumentException;

class SignalManager
{
    public function __construct(
        protected FirehoseConsumer $firehoseConsumer,
        protected JetstreamConsumer $jetstreamConsumer,
    ) {
    }

    /**
     * Start consuming events from the AT Protocol.
     */
    public function start(?int $cursor = null): void
    {
        $this->resolveConsumer()->start($cursor);
    }

    /**
     * Stop consuming events.
     */
    public function stop(): void
    {
        $this->resolveConsumer()->stop();
    }

    /**
     * Get the current consumer mode.
     */
    public function getMode(): string
    {
        return config('signal.mode', 'jetstream');
    }

    /**
     * Resolve the appropriate consumer based on configuration.
     */
    protected function resolveConsumer(): FirehoseConsumer|JetstreamConsumer
    {
        $mode = $this->getMode();

        return match ($mode) {
            'firehose' => $this->firehoseConsumer,
            'jetstream' => $this->jetstreamConsumer,
            default => throw new InvalidArgumentException("Invalid signal mode: {$mode}. Must be 'jetstream' or 'firehose'."),
        };
    }
}
