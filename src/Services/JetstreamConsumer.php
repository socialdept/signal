<?php

namespace SocialDept\Signal\Services;

use Illuminate\Support\Facades\Log;
use Ratchet\Client\Connector;
use Ratchet\Client\WebSocket;
use React\EventLoop\Loop;
use SocialDept\Signal\Contracts\CursorStore;
use SocialDept\Signal\Events\JetstreamEvent;
use SocialDept\Signal\Exceptions\ConnectionException;

class JetstreamConsumer
{
    protected CursorStore $cursorStore;
    protected SignalRegistry $signalRegistry;
    protected EventDispatcher $eventDispatcher;
    protected ?WebSocket $connection = null;
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
        $loop = Loop::get();
        $connector = new Connector($loop);

        $connector($url)->then(
            function (WebSocket $conn) {
                $this->connection = $conn;
                $this->reconnectAttempts = 0;

                Log::info('Signal: Connected to Jetstream');

                $conn->on('message', function ($msg) {
                    $this->handleMessage($msg);
                });

                $conn->on('close', function ($code, $reason) {
                    Log::warning('Signal: Connection closed', [
                        'code' => $code,
                        'reason' => $reason,
                    ]);

                    if (!$this->shouldStop) {
                        $this->attemptReconnect();
                    }
                });

                $conn->on('error', function (\Exception $e) {
                    Log::error('Signal: Connection error', [
                        'error' => $e->getMessage(),
                    ]);
                });

                // Setup ping interval to keep connection alive
                $this->setupPingInterval($conn, $loop);
            },
            function (\Exception $e) {
                Log::error('Signal: Could not connect to Jetstream', [
                    'error' => $e->getMessage(),
                ]);

                if (!$this->shouldStop) {
                    $this->attemptReconnect();
                }
            }
        );

        $loop->run();
    }

    /**
     * Handle incoming WebSocket message.
     */
    protected function handleMessage($message): void
    {
        try {
            $data = json_decode($message, true);

            if (!$data) {
                Log::warning('Signal: Failed to decode message');
                return;
            }

            $event = JetstreamEvent::fromArray($data);

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
     * Attempt to reconnect to the Jetstream.
     */
    protected function attemptReconnect(): void
    {
        $maxAttempts = config('signal.connection.reconnect_attempts', 5);
        $delay = config('signal.connection.reconnect_delay', 5);

        if ($this->reconnectAttempts >= $maxAttempts) {
            Log::error('Signal: Max reconnection attempts reached');
            throw new ConnectionException('Failed to reconnect to Jetstream after ' . $maxAttempts . ' attempts');
        }

        $this->reconnectAttempts++;

        Log::info('Signal: Attempting to reconnect', [
            'attempt' => $this->reconnectAttempts,
            'max_attempts' => $maxAttempts,
        ]);

        sleep($delay);

        $cursor = $this->cursorStore->get();
        $url = $this->buildWebSocketUrl($cursor);

        $this->connect($url);
    }

    /**
     * Setup ping interval to keep connection alive.
     */
    protected function setupPingInterval(WebSocket $conn, $loop): void
    {
        $interval = config('signal.connection.ping_interval', 30);

        $loop->addPeriodicTimer($interval, function () use ($conn) {
            if ($conn->getReadyState() === WebSocket::STATE_OPEN) {
                $conn->send(json_encode(['type' => 'ping']));
            }
        });
    }

    /**
     * Build the WebSocket URL with optional cursor.
     */
    protected function buildWebSocketUrl(?int $cursor = null): string
    {
        $baseUrl = config('signal.websocket_url', 'wss://jetstream2.us-east.bsky.network');
        $url = rtrim($baseUrl, '/') . '/subscribe';

        if ($cursor !== null) {
            $url .= '?cursor=' . $cursor;
        }

        return $url;
    }
}
