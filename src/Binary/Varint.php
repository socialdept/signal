<?php

declare(strict_types=1);

namespace SocialDept\AtpSignals\Binary;

use RuntimeException;

/**
 * Varint (Variable-Length Integer) decoder for unsigned integers.
 *
 * Used in CAR format to encode block lengths. Implements the same
 * varint encoding used in Protocol Buffers and other binary formats.
 */
class Varint
{
    /**
     * Decode a varint from the beginning of the data.
     *
     * @param string $data Binary data containing varint
     * @param int $offset Starting position (will be updated to position after varint)
     * @return int Decoded unsigned integer
     * @throws RuntimeException If varint is malformed or data ends unexpectedly
     */
    public static function decode(string $data, int &$offset = 0): int
    {
        $result = 0;
        $shift = 0;
        $length = strlen($data);

        while ($offset < $length) {
            $byte = ord($data[$offset]);
            $offset++;

            // Take lower 7 bits and shift into result
            $result |= ($byte & 0x7F) << $shift;

            // If MSB is 0, we're done
            if (($byte & 0x80) === 0) {
                return $result;
            }

            $shift += 7;

            // Prevent overflow (64-bit max)
            if ($shift > 63) {
                throw new RuntimeException('Varint too long (max 64 bits)');
            }
        }

        throw new RuntimeException('Unexpected end of varint data');
    }

    /**
     * Read a varint and return both the value and remaining data.
     *
     * @param string $data Binary data starting with varint
     * @return array{0: int, 1: string} [decoded value, remaining data]
     */
    public static function decodeFirst(string $data): array
    {
        $offset = 0;
        $value = self::decode($data, $offset);
        $remainder = substr($data, $offset);

        return [$value, $remainder];
    }
}
