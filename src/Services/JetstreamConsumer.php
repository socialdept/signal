<?php

namespace SocialDept\AtpSignals\Services;

use Illuminate\Support\Facades\Log;
use SocialDept\AtpSignals\Contracts\CursorStore;
use SocialDept\AtpSignals\Events\SignalEvent;
use SocialDept\AtpSignals\Exceptions\ConnectionException;
use SocialDept\AtpSignals\Support\WebSocketConnection;

class JetstreamConsumer
{
    protected CursorStore $cursorStore;

    protected SignalRegistry $signalRegistry;

    protected EventDispatcher $eventDispatcher;

    protected ?WebSocketConnection $connection = null;

    protected int $reconnectAttempts = 0;

    protected bool $shouldStop = false;

    protected ?\Exception $lastError = null;

    public function __construct(
        CursorStore $cursorStore,
        SignalRegistry $signalRegistry,
        EventDispatcher $eventDispatcher
    ) {
        $this->cursorStore = $cursorStore;
        $this->signalRegistry = $signalRegistry;
        $this->eventDispatcher = $eventDispatcher;
    }

    /**
     * Start consuming the Jetstream.
     */
    public function start(?int $cursor = null): void
    {
        $this->shouldStop = false;
        $this->lastError = null;

        // Get cursor from storage if not explicitly provided
        // null = use stored cursor, 0 = start fresh (no cursor), >0 = specific cursor
        if ($cursor === null) {
            $cursor = $this->cursorStore->get();
        }

        // If cursor is explicitly 0, don't send it (fresh start)
        $url = $this->buildWebSocketUrl($cursor > 0 ? $cursor : null);

        Log::info('Signal: Starting Jetstream consumer', [
            'url' => $url,
            'cursor' => $cursor > 0 ? $cursor : 'none (fresh start)',
            'mode' => 'jetstream',
        ]);

        $this->connect($url);

        // Check if we exited due to a fatal error (after all reconnection attempts)
        if ($this->lastError) {
            throw $this->lastError;
        }

        // If we get here without intentionally stopping, something went wrong
        if (! $this->shouldStop) {
            throw new ConnectionException('Jetstream connection closed unexpectedly');
        }
    }

    /**
     * Stop consuming the Jetstream.
     */
    public function stop(): void
    {
        $this->shouldStop = true;

        if ($this->connection) {
            $this->connection->close();
        }

        Log::info('Signal: Jetstream consumer stopped');
    }

    /**
     * Connect to the Jetstream WebSocket.
     */
    protected function connect(string $url): void
    {
        $this->connection = new WebSocketConnection();

        // Set up event handlers
        $this->connection
            ->onMessage(function (string $message) {
                $this->handleMessage($message);
            })
            ->onClose(function (?int $code, ?string $reason) {
                $this->handleClose($code, $reason);
            })
            ->onError(function (\Exception $e) {
                $this->handleError($e);
            });

        // Connect to the WebSocket endpoint
        $this->connection->connect($url)->then(
            function () {
                $this->reconnectAttempts = 0;
                Log::info('Signal: Connected to Jetstream successfully');
            },
            function (\Exception $e) {
                Log::error('Signal: Could not connect to Jetstream', [
                    'error' => $e->getMessage(),
                ]);

                if (! $this->shouldStop) {
                    $this->attemptReconnect();
                }
            }
        );

        // Run the event loop (blocking)
        $this->connection->run();
    }

    /**
     * Handle incoming WebSocket message.
     */
    protected function handleMessage(string $message): void
    {
        try {
            $data = json_decode($message, true);

            if (! $data) {
                Log::warning('Signal: Failed to decode message');

                return;
            }

            $event = SignalEvent::fromArray($data);

            // Update cursor
            $this->cursorStore->set($event->timeUs);

            // Dispatch to matching signals
            $this->eventDispatcher->dispatch($event);

        } catch (\Exception $e) {
            Log::error('Signal: Error handling message', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

    /**
     * Handle WebSocket connection close.
     */
    protected function handleClose(?int $code, ?string $reason): void
    {
        Log::warning('Signal: Connection closed', [
            'code' => $code,
            'reason' => $reason ?: 'none',
            'reconnect_attempts' => $this->reconnectAttempts,
        ]);

        // Attempt reconnection if enabled
        if (! $this->shouldStop) {
            $this->attemptReconnect();
        }
    }

    /**
     * Handle WebSocket connection error.
     */
    protected function handleError(\Exception $error): void
    {
        Log::error('Signal: Connection error', [
            'error' => $error->getMessage(),
            'trace' => $error->getTraceAsString(),
        ]);
    }

    /**
     * Attempt to reconnect to the Jetstream with exponential backoff.
     */
    protected function attemptReconnect(): void
    {
        $maxAttempts = config('signal.connection.reconnect_attempts', 5);

        if ($this->reconnectAttempts >= $maxAttempts) {
            Log::error('Signal: Max reconnection attempts reached');

            $this->lastError = new ConnectionException('Failed to reconnect to Jetstream after '.$maxAttempts.' attempts');
            $this->connection?->stop();

            return;
        }

        $this->reconnectAttempts++;

        // Calculate exponential backoff delay
        $baseDelay = config('signal.connection.reconnect_delay', 5);
        $maxDelay = config('signal.connection.max_reconnect_delay', 60);

        $delay = min(
            $baseDelay * (2 ** ($this->reconnectAttempts - 1)),
            $maxDelay
        );

        Log::info('Signal: Attempting to reconnect', [
            'attempt' => $this->reconnectAttempts,
            'max_attempts' => $maxAttempts,
            'delay' => $delay,
        ]);

        sleep($delay);

        $cursor = $this->cursorStore->get();
        $url = $this->buildWebSocketUrl($cursor);

        $this->connect($url);
    }

    /**
     * Build the WebSocket URL with optional cursor and collection filters.
     */
    protected function buildWebSocketUrl(?int $cursor = null): string
    {
        $baseUrl = config('signal.websocket_url', 'wss://jetstream2.us-east.bsky.network');
        $url = rtrim($baseUrl, '/').'/subscribe';

        $params = [];

        // Add cursor parameter if provided
        if ($cursor !== null) {
            $params[] = 'cursor='.$cursor;
        }

        // Add collection filters from all registered signals
        // If ANY signal wants all collections (returns null), don't filter at all
        $signals = $this->signalRegistry->all();
        $hasWildcardSignal = $signals->contains(fn ($signal) => $signal->collections() === null);

        Log::debug('Signal: Building Jetstream URL', [
            'registered_signals' => $signals->map(fn ($s) => get_class($s))->values()->toArray(),
            'has_wildcard_signal' => $hasWildcardSignal,
        ]);

        if (! $hasWildcardSignal) {
            $collections = $signals
                ->flatMap(fn ($signal) => $signal->collections() ?? [])
                ->unique()
                ->filter()
                ->values();

            Log::debug('Signal: Collection filters', [
                'collections' => $collections->toArray(),
            ]);

            if ($collections->isNotEmpty()) {
                foreach ($collections as $collection) {
                    // Don't encode wildcards - Jetstream expects literal *
                    $encoded = str_replace('%2A', '*', urlencode($collection));
                    $params[] = 'wantedCollections='.$encoded;
                }
            }
        }

        if (! empty($params)) {
            $url .= '?'.implode('&', $params);
        }

        return $url;
    }
}
