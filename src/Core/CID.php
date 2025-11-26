<?php

declare(strict_types=1);

namespace SocialDept\AtpSignals\Core;

use RuntimeException;
use SocialDept\AtpSignals\Binary\Reader;
use SocialDept\AtpSignals\Binary\Varint;

/**
 * Content Identifier (CID) parser for IPLD.
 *
 * Supports CIDv0 (base58btc) and CIDv1 (multibase).
 * Minimal implementation for reading CIDs from CBOR and CAR data.
 */
class CID
{
    private const BASE32_CHARSET = 'abcdefghijklmnopqrstuvwxyz234567';
    private const BASE58BTC_CHARSET = '123456789ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz';

    public function __construct(
        public readonly int $version,
        public readonly int $codec,
        public readonly string $hash,
    ) {
    }

    /**
     * Parse CID from binary data.
     *
     * @param string $data Binary CID data
     * @return self
     * @throws RuntimeException If CID is malformed
     */
    public static function fromBinary(string $data): self
    {
        $reader = new Reader($data);

        // Read version
        $version = $reader->readVarint();

        if ($version === 0x12) {
            // CIDv0 (legacy format - starts with multihash directly)
            // Reset and read as v0
            $reader = new Reader($data);
            $hashType = $reader->readVarint(); // 0x12 = sha256
            $hashLength = $reader->readVarint(); // typically 32
            $hash = $reader->readBytes($hashLength);

            return new self(
                version: 0,
                codec: 0x70, // dag-pb
                hash: chr($hashType) . chr($hashLength) . $hash,
            );
        }

        if ($version !== 1) {
            throw new RuntimeException("Unsupported CID version: {$version}");
        }

        // Read codec
        $codec = $reader->readVarint();

        // Read multihash (hash type + length + hash bytes)
        $hashType = $reader->readVarint();
        $hashLength = $reader->readVarint();
        $hashBytes = $reader->readBytes($hashLength);

        // Store complete multihash
        $hash = chr($hashType) . chr($hashLength) . $hashBytes;

        return new self(
            version: $version,
            codec: $codec,
            hash: $hash,
        );
    }

    /**
     * Parse CID from string (base32 or base58btc).
     *
     * @param string $str CID string
     * @return self
     * @throws RuntimeException If CID string is invalid
     */
    public static function fromString(string $str): self
    {
        if (empty($str)) {
            throw new RuntimeException('Empty CID string');
        }

        // Check multibase prefix
        $prefix = $str[0];

        if ($prefix === 'b') {
            // base32 (CIDv1)
            $binary = self::decodeBase32(substr($str, 1));

            return self::fromBinary($binary);
        }

        if ($prefix === 'Q' || $prefix === '1') {
            // base58btc (likely CIDv0)
            $binary = self::decodeBase58($str);

            return self::fromBinary($binary);
        }

        throw new RuntimeException("Unsupported multibase prefix: {$prefix}");
    }

    /**
     * Convert CID to string representation.
     */
    public function toString(): string
    {
        if ($this->version === 0) {
            // CIDv0 is always base58btc without prefix
            return self::encodeBase58($this->hash);
        }

        // CIDv1 uses base32 with 'b' prefix
        $binary = chr($this->version) . $this->encodeVarint($this->codec) . $this->hash;

        return 'b' . self::encodeBase32($binary);
    }

    /**
     * Get binary representation.
     */
    public function toBinary(): string
    {
        if ($this->version === 0) {
            return $this->hash;
        }

        return chr($this->version) . $this->encodeVarint($this->codec) . $this->hash;
    }

    /**
     * Decode base32 string to binary.
     */
    private static function decodeBase32(string $str): string
    {
        $str = strtolower($str);
        $result = '';
        $bits = 0;
        $value = 0;

        for ($i = 0; $i < strlen($str); $i++) {
            $char = $str[$i];
            $pos = strpos(self::BASE32_CHARSET, $char);

            if ($pos === false) {
                throw new RuntimeException("Invalid base32 character: {$char}");
            }

            $value = ($value << 5) | $pos;
            $bits += 5;

            if ($bits >= 8) {
                $result .= chr(($value >> ($bits - 8)) & 0xFF);
                $bits -= 8;
            }
        }

        return $result;
    }

    /**
     * Encode binary to base32 string.
     */
    private static function encodeBase32(string $data): string
    {
        $result = '';
        $bits = 0;
        $value = 0;

        for ($i = 0; $i < strlen($data); $i++) {
            $value = ($value << 8) | ord($data[$i]);
            $bits += 8;

            while ($bits >= 5) {
                $result .= self::BASE32_CHARSET[($value >> ($bits - 5)) & 0x1F];
                $bits -= 5;
            }
        }

        if ($bits > 0) {
            $result .= self::BASE32_CHARSET[($value << (5 - $bits)) & 0x1F];
        }

        return $result;
    }

    /**
     * Decode base58btc string to binary.
     */
    private static function decodeBase58(string $str): string
    {
        $decoded = gmp_init(0);
        $base = gmp_init(58);

        for ($i = 0; $i < strlen($str); $i++) {
            $char = $str[$i];
            $pos = strpos(self::BASE58BTC_CHARSET, $char);

            if ($pos === false) {
                throw new RuntimeException("Invalid base58 character: {$char}");
            }

            $decoded = gmp_add(gmp_mul($decoded, $base), gmp_init($pos));
        }

        $hex = gmp_strval($decoded, 16);
        if (strlen($hex) % 2) {
            $hex = '0' . $hex;
        }

        // Add leading zeros
        for ($i = 0; $i < strlen($str) && $str[$i] === '1'; $i++) {
            $hex = '00' . $hex;
        }

        return hex2bin($hex);
    }

    /**
     * Encode binary to base58btc string.
     */
    private static function encodeBase58(string $data): string
    {
        $num = gmp_init('0x' . bin2hex($data));
        $base = gmp_init(58);
        $result = '';

        while (gmp_cmp($num, 0) > 0) {
            [$num, $remainder] = gmp_div_qr($num, $base);
            $result = self::BASE58BTC_CHARSET[gmp_intval($remainder)] . $result;
        }

        // Add leading '1's for leading zero bytes
        for ($i = 0; $i < strlen($data) && ord($data[$i]) === 0; $i++) {
            $result = '1' . $result;
        }

        return $result;
    }

    /**
     * Encode varint for CID binary format.
     */
    private function encodeVarint(int $value): string
    {
        $result = '';

        while ($value >= 0x80) {
            $result .= chr(($value & 0x7F) | 0x80);
            $value >>= 7;
        }

        $result .= chr($value);

        return $result;
    }

    public function __toString(): string
    {
        return $this->toString();
    }
}
