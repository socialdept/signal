<?php

declare(strict_types=1);

namespace SocialDept\Signal\CBOR;

use RuntimeException;
use SocialDept\Signal\Binary\Reader;
use SocialDept\Signal\Core\CID;

/**
 * CBOR (Concise Binary Object Representation) decoder.
 *
 * Implements RFC 8949 CBOR with DAG-CBOR extensions for IPLD.
 * Supports tag 42 for CID links.
 */
class Decoder
{
    private const MAJOR_TYPE_UNSIGNED = 0;

    private const MAJOR_TYPE_NEGATIVE = 1;

    private const MAJOR_TYPE_BYTES = 2;

    private const MAJOR_TYPE_TEXT = 3;

    private const MAJOR_TYPE_ARRAY = 4;

    private const MAJOR_TYPE_MAP = 5;

    private const MAJOR_TYPE_TAG = 6;

    private const MAJOR_TYPE_SPECIAL = 7;

    private const TAG_CID = 42;

    private Reader $reader;

    public function __construct(string $data)
    {
        $this->reader = new Reader($data);
    }

    /**
     * Decode the next CBOR item.
     *
     * @return mixed Decoded value
     *
     * @throws RuntimeException If data is malformed
     */
    public function decode(): mixed
    {
        if (! $this->reader->hasMore()) {
            throw new RuntimeException('Unexpected end of CBOR data');
        }

        $initialByte = $this->reader->readByte();
        $majorType = $initialByte >> 5;
        $additionalInfo = $initialByte & 0x1F;

        return match ($majorType) {
            self::MAJOR_TYPE_UNSIGNED => $this->decodeUnsigned($additionalInfo),
            self::MAJOR_TYPE_NEGATIVE => $this->decodeNegative($additionalInfo),
            self::MAJOR_TYPE_BYTES => $this->decodeBytes($additionalInfo),
            self::MAJOR_TYPE_TEXT => $this->decodeText($additionalInfo),
            self::MAJOR_TYPE_ARRAY => $this->decodeArray($additionalInfo),
            self::MAJOR_TYPE_MAP => $this->decodeMap($additionalInfo),
            self::MAJOR_TYPE_TAG => $this->decodeTag($additionalInfo),
            self::MAJOR_TYPE_SPECIAL => $this->decodeSpecial($additionalInfo),
            default => throw new RuntimeException("Unknown major type: {$majorType}"),
        };
    }

    /**
     * Check if there's more data to decode.
     */
    public function hasMore(): bool
    {
        return $this->reader->hasMore();
    }

    /**
     * Get current position.
     */
    public function getPosition(): int
    {
        return $this->reader->getPosition();
    }

    /**
     * Decode unsigned integer.
     */
    private function decodeUnsigned(int $additionalInfo): int
    {
        return $this->decodeLength($additionalInfo);
    }

    /**
     * Decode negative integer.
     */
    private function decodeNegative(int $additionalInfo): int
    {
        $value = $this->decodeLength($additionalInfo);

        return -1 - $value;
    }

    /**
     * Decode byte string.
     */
    private function decodeBytes(int $additionalInfo): string
    {
        $length = $this->decodeLength($additionalInfo);

        return $this->reader->readBytes($length);
    }

    /**
     * Decode text string.
     */
    private function decodeText(int $additionalInfo): string
    {
        $length = $this->decodeLength($additionalInfo);

        return $this->reader->readBytes($length);
    }

    /**
     * Decode array.
     */
    private function decodeArray(int $additionalInfo): array
    {
        $length = $this->decodeLength($additionalInfo);
        $array = [];

        for ($i = 0; $i < $length; $i++) {
            $array[] = $this->decode();
        }

        return $array;
    }

    /**
     * Decode map (object).
     */
    private function decodeMap(int $additionalInfo): array
    {
        $length = $this->decodeLength($additionalInfo);
        $map = [];

        for ($i = 0; $i < $length; $i++) {
            $key = $this->decode();
            $value = $this->decode();

            if (! is_string($key) && ! is_int($key)) {
                throw new RuntimeException('Map keys must be strings or integers');
            }

            $map[$key] = $value;
        }

        return $map;
    }

    /**
     * Decode tagged value.
     */
    private function decodeTag(int $additionalInfo): mixed
    {
        $tag = $this->decodeLength($additionalInfo);

        if ($tag === self::TAG_CID) {
            // Tag 42 = CID link (DAG-CBOR)
            // Next item should be byte string containing CID
            $cidBytes = $this->decode();

            if (! is_string($cidBytes)) {
                throw new RuntimeException('CID tag must be followed by byte string');
            }

            // First byte should be 0x00 for CID
            if (ord($cidBytes[0]) !== 0x00) {
                throw new RuntimeException('Invalid CID byte string prefix');
            }

            return CID::fromBinary(substr($cidBytes, 1));
        }

        // For other tags, just return the tagged value
        return $this->decode();
    }

    /**
     * Decode special values (bool, null, floats).
     */
    private function decodeSpecial(int $additionalInfo): mixed
    {
        return match ($additionalInfo) {
            20 => false,
            21 => true,
            22 => null,
            23 => throw new RuntimeException('Undefined special value'),
            25 => $this->decodeFloat16(), // IEEE 754 Half-Precision (16-bit)
            26 => $this->decodeFloat32(), // IEEE 754 Single-Precision (32-bit)
            27 => $this->decodeFloat64(), // IEEE 754 Double-Precision (64-bit)
            default => throw new RuntimeException("Unsupported special value: {$additionalInfo}"),
        };
    }

    /**
     * Decode IEEE 754 half-precision float (16-bit).
     */
    private function decodeFloat16(): float
    {
        $bytes = $this->reader->readBytes(2);
        $bits = unpack('n', $bytes)[1];

        // Extract sign, exponent, and mantissa
        $sign = ($bits >> 15) & 1;
        $exponent = ($bits >> 10) & 0x1F;
        $mantissa = $bits & 0x3FF;

        // Handle special cases
        if ($exponent === 0) {
            // Subnormal or zero
            $value = $mantissa / 1024.0 * (2 ** -14);
        } elseif ($exponent === 31) {
            // Infinity or NaN
            return $mantissa === 0 ? ($sign ? -INF : INF) : NAN;
        } else {
            // Normalized value
            $value = (1 + $mantissa / 1024.0) * (2 ** ($exponent - 15));
        }

        return $sign ? -$value : $value;
    }

    /**
     * Decode IEEE 754 single-precision float (32-bit).
     */
    private function decodeFloat32(): float
    {
        $bytes = $this->reader->readBytes(4);

        return unpack('G', $bytes)[1]; // Big-endian float
    }

    /**
     * Decode IEEE 754 double-precision float (64-bit).
     */
    private function decodeFloat64(): float
    {
        $bytes = $this->reader->readBytes(8);

        return unpack('E', $bytes)[1]; // Big-endian double
    }

    /**
     * Decode length/value from additional info.
     */
    private function decodeLength(int $additionalInfo): int
    {
        if ($additionalInfo < 24) {
            return $additionalInfo;
        }

        return match ($additionalInfo) {
            24 => $this->reader->readByte(),
            25 => unpack('n', $this->reader->readBytes(2))[1],
            26 => unpack('N', $this->reader->readBytes(4))[1],
            27 => $this->readUint64(),
            default => throw new RuntimeException("Invalid additional info: {$additionalInfo}"),
        };
    }

    /**
     * Read 64-bit unsigned integer.
     */
    private function readUint64(): int
    {
        $bytes = $this->reader->readBytes(8);
        $unpacked = unpack('J', $bytes)[1];

        return $unpacked;
    }
}
