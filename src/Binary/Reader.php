<?php

declare(strict_types=1);

namespace SocialDept\Signals\Binary;

use RuntimeException;

/**
 * Binary data reader with position tracking.
 *
 * Provides stream-like interface for reading from binary strings.
 */
class Reader
{
    private int $position = 0;

    public function __construct(
        private readonly string $data,
    ) {
    }

    /**
     * Get current position in the data.
     */
    public function getPosition(): int
    {
        return $this->position;
    }

    /**
     * Get total length of data.
     */
    public function getLength(): int
    {
        return strlen($this->data);
    }

    /**
     * Check if there's more data to read.
     */
    public function hasMore(): bool
    {
        return $this->position < strlen($this->data);
    }

    /**
     * Get remaining bytes count.
     */
    public function remaining(): int
    {
        return strlen($this->data) - $this->position;
    }

    /**
     * Peek at the next byte without advancing position.
     *
     * @throws RuntimeException If no more data available
     */
    public function peek(): int
    {
        if (! $this->hasMore()) {
            throw new RuntimeException('Unexpected end of data');
        }

        return ord($this->data[$this->position]);
    }

    /**
     * Read a single byte and advance position.
     *
     * @throws RuntimeException If no more data available
     */
    public function readByte(): int
    {
        $byte = $this->peek();
        $this->position++;

        return $byte;
    }

    /**
     * Read exactly N bytes and advance position.
     *
     * @throws RuntimeException If not enough data available
     */
    public function readBytes(int $length): string
    {
        if ($this->remaining() < $length) {
            throw new RuntimeException("Cannot read {$length} bytes, only {$this->remaining()} remaining");
        }

        $bytes = substr($this->data, $this->position, $length);
        $this->position += $length;

        return $bytes;
    }

    /**
     * Read a varint (variable-length integer).
     *
     * @throws RuntimeException If varint is malformed
     */
    public function readVarint(): int
    {
        return Varint::decode($this->data, $this->position);
    }

    /**
     * Get all remaining data without advancing position.
     */
    public function peekRemaining(): string
    {
        return substr($this->data, $this->position);
    }

    /**
     * Read all remaining data and advance position to end.
     */
    public function readRemaining(): string
    {
        $remaining = $this->peekRemaining();
        $this->position = strlen($this->data);

        return $remaining;
    }

    /**
     * Skip N bytes forward.
     *
     * @throws RuntimeException If trying to skip past end
     */
    public function skip(int $bytes): void
    {
        if ($this->remaining() < $bytes) {
            throw new RuntimeException("Cannot skip {$bytes} bytes, only {$this->remaining()} remaining");
        }

        $this->position += $bytes;
    }
}
