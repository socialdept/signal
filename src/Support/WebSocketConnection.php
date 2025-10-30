<?php

namespace SocialDept\Signal\Support;

use Ratchet\Client\Connector;
use Ratchet\Client\WebSocket;
use Ratchet\RFC6455\Messaging\MessageInterface;
use React\EventLoop\Loop;
use React\EventLoop\LoopInterface;
use React\Promise\PromiseInterface;

/**
 * WebSocket connection wrapper for AT Protocol Jetstream.
 */
class WebSocketConnection
{
    protected ?WebSocket $connection = null;
    protected LoopInterface $loop;
    protected bool $connected = false;
    protected ?\Closure $onMessage = null;
    protected ?\Closure $onClose = null;
    protected ?\Closure $onError = null;

    public function __construct(?LoopInterface $loop = null)
    {
        $this->loop = $loop ?? Loop::get();
    }

    /**
     * Connect to a WebSocket endpoint.
     */
    public function connect(string $url): PromiseInterface
    {
        $connector = new Connector($this->loop);

        return $connector($url)->then(
            function (WebSocket $conn) {
                $this->connection = $conn;
                $this->connected = true;

                // Register event handlers
                $conn->on('message', function (MessageInterface $msg) {
                    if ($this->onMessage) {
                        ($this->onMessage)($msg->getPayload());
                    }
                });

                $conn->on('close', function ($code = null, $reason = null) {
                    $this->connected = false;
                    if ($this->onClose) {
                        ($this->onClose)($code, $reason);
                    }
                });

                $conn->on('error', function (\Exception $e) {
                    if ($this->onError) {
                        ($this->onError)($e);
                    }
                });

                return $conn;
            },
            function (\Exception $e) {
                if ($this->onError) {
                    ($this->onError)($e);
                }
                throw $e;
            }
        );
    }

    /**
     * Send a message through the WebSocket connection.
     */
    public function send(string $message): bool
    {
        if (!$this->connected || !$this->connection) {
            return false;
        }

        try {
            $this->connection->send($message);
            return true;
        } catch (\Exception $e) {
            if ($this->onError) {
                ($this->onError)($e);
            }
            return false;
        }
    }

    /**
     * Close the WebSocket connection.
     */
    public function close(int $code = 1000, string $reason = ''): void
    {
        if ($this->connection && $this->connected) {
            $this->connection->close($code, $reason);
            $this->connected = false;
        }
    }

    /**
     * Check if the connection is currently active.
     */
    public function isConnected(): bool
    {
        return $this->connected;
    }

    /**
     * Set the message handler callback.
     */
    public function onMessage(callable $callback): self
    {
        $this->onMessage = $callback(...);
        return $this;
    }

    /**
     * Set the close handler callback.
     */
    public function onClose(callable $callback): self
    {
        $this->onClose = $callback(...);
        return $this;
    }

    /**
     * Set the error handler callback.
     */
    public function onError(callable $callback): self
    {
        $this->onError = $callback(...);
        return $this;
    }

    /**
     * Get the event loop instance.
     */
    public function getLoop(): LoopInterface
    {
        return $this->loop;
    }

    /**
     * Start the event loop (blocking).
     */
    public function run(): void
    {
        $this->loop->run();
    }

    /**
     * Stop the event loop.
     */
    public function stop(): void
    {
        $this->close();
        $this->loop->stop();
    }

    /**
     * Get the underlying WebSocket connection.
     */
    public function getConnection(): ?WebSocket
    {
        return $this->connection;
    }
}
