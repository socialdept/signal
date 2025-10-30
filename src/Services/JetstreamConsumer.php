<?php

namespace SocialDept\Signal\Services;

use Illuminate\Support\Facades\Log;
use SocialDept\Signal\Contracts\CursorStore;
use SocialDept\Signal\Events\SignalEvent;
use SocialDept\Signal\Exceptions\ConnectionException;
use SocialDept\Signal\Support\WebSocketConnection;

class JetstreamConsumer
{
    protected CursorStore $cursorStore;

    protected SignalRegistry $signalRegistry;

    protected EventDispatcher $eventDispatcher;

    protected ?WebSocketConnection $connection = null;

    protected int $reconnectAttempts = 0;

    protected bool $shouldStop = false;

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

        // Get cursor from storage if not provided
        if ($cursor === null) {
            $cursor = $this->cursorStore->get();
        }

        $url = $this->buildWebSocketUrl($cursor);

        Log::info('Signal: Starting Jetstream consumer', [
            'url' => $url,
            'cursor' => $cursor,
        ]);

        $this->connect($url);
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
        $this->connection = new WebSocketConnection;

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

            // Check if any signals match this event
            $matchingSignals = $this->signalRegistry->getMatchingSignals($event);

            if ($matchingSignals->isNotEmpty()) {
                $collection = $event->getCollection() ?? $event->kind;
                $operation = $event->getOperation() ?? 'event';

                Log::info('Signal: Event matched', [
                    'collection' => $collection,
                    'operation' => $operation,
                    'matched_signals' => $matchingSignals->count(),
                    'signal_names' => $matchingSignals->map(fn ($s) => class_basename($s))->join(', '),
                ]);
            }

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
            throw new ConnectionException('Failed to reconnect to Jetstream after '.$maxAttempts.' attempts');
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
        $collections = $this->signalRegistry->all()
            ->flatMap(fn ($signal) => $signal->collections() ?? [])
            ->unique()
            ->filter()
            ->values();

        if ($collections->isNotEmpty()) {
            foreach ($collections as $collection) {
                $params[] = 'wantedCollections='.urlencode($collection);
            }

            Log::info('Signal: Collection filters applied', [
                'collections' => $collections->toArray(),
            ]);
        } else {
            Log::warning('Signal: No collection filters - will receive ALL events');
        }

        if (! empty($params)) {
            $url .= '?'.implode('&', $params);
        }

        return $url;
    }
}
