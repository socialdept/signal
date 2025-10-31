<?php

declare(strict_types=1);

namespace SocialDept\Signal\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SocialDept\Signal\Core\CBOR;
use SocialDept\Signal\Core\CID;

class CBORTest extends TestCase
{
    public function test_decode_unsigned_integers(): void
    {
        // Small value (0-23)
        $this->assertSame(0, CBOR::decode("\x00"));
        $this->assertSame(1, CBOR::decode("\x01"));
        $this->assertSame(23, CBOR::decode("\x17"));

        // 1-byte value
        $this->assertSame(24, CBOR::decode("\x18\x18"));
        $this->assertSame(255, CBOR::decode("\x18\xFF"));

        // 2-byte value
        $this->assertSame(256, CBOR::decode("\x19\x01\x00"));
        $this->assertSame(1000, CBOR::decode("\x19\x03\xE8"));
    }

    public function test_decode_negative_integers(): void
    {
        // -1 is encoded as 0x20 (major type 1, value 0)
        $this->assertSame(-1, CBOR::decode("\x20"));

        // -10 is encoded as 0x29 (major type 1, value 9)
        $this->assertSame(-10, CBOR::decode("\x29"));

        // -100 is encoded as 0x38 0x63 (major type 1, 1-byte value 99)
        $this->assertSame(-100, CBOR::decode("\x38\x63"));
    }

    public function test_decode_byte_strings(): void
    {
        // Empty byte string
        $this->assertSame('', CBOR::decode("\x40"));

        // 4-byte string
        $this->assertSame("\x01\x02\x03\x04", CBOR::decode("\x44\x01\x02\x03\x04"));
    }

    public function test_decode_text_strings(): void
    {
        // Empty text string
        $this->assertSame('', CBOR::decode("\x60"));

        // "hello"
        $this->assertSame('hello', CBOR::decode("\x65hello"));

        // "IETF"
        $this->assertSame('IETF', CBOR::decode("\x64IETF"));
    }

    public function test_decode_arrays(): void
    {
        // Empty array
        $this->assertSame([], CBOR::decode("\x80"));

        // [1, 2, 3]
        $this->assertSame([1, 2, 3], CBOR::decode("\x83\x01\x02\x03"));

        // Mixed array [1, "two", 3]
        $result = CBOR::decode("\x83\x01\x63two\x03");
        $this->assertSame([1, 'two', 3], $result);
    }

    public function test_decode_maps(): void
    {
        // Empty map
        $this->assertSame([], CBOR::decode("\xA0"));

        // {"a": 1, "b": 2}
        $result = CBOR::decode("\xA2\x61a\x01\x61b\x02");
        $this->assertSame(['a' => 1, 'b' => 2], $result);
    }

    public function test_decode_special_values(): void
    {
        // false
        $this->assertFalse(CBOR::decode("\xF4"));

        // true
        $this->assertTrue(CBOR::decode("\xF5"));

        // null
        $this->assertNull(CBOR::decode("\xF6"));
    }

    public function test_decode_first_returns_value_and_remainder(): void
    {
        [$value, $remainder] = CBOR::decodeFirst("\x01\x02\x03");

        $this->assertSame(1, $value);
        $this->assertSame("\x02\x03", $remainder);
    }

    public function test_decode_nested_structures(): void
    {
        // {"key": [1, 2, {"inner": true}]}
        $cbor = "\xA1\x63key\x83\x01\x02\xA1\x65inner\xF5";
        $result = CBOR::decode($cbor);

        $expected = [
            'key' => [1, 2, ['inner' => true]],
        ];

        $this->assertSame($expected, $result);
    }

    public function test_decode_cid_tag(): void
    {
        // Tag 42 (CID) followed by byte string with CID data
        // CID bytes: 0x00 prefix + version + codec + multihash
        $cidBinary = "\x01\x71\x12\x20" . str_repeat("\x00", 32); // version 1, codec 0x71, sha256, 32 zero bytes
        $cidBytes = "\x00" . $cidBinary; // Add 0x00 prefix for CBOR tag 42

        // CBOR: tag 42 (0xD8 0x2A) + byte string with 1-byte length (0x58 = major type 2, additional info 24)
        $length = strlen($cidBytes);
        $cbor = "\xD8\x2A\x58" . chr($length) . $cidBytes;

        $result = CBOR::decode($cbor);

        $this->assertInstanceOf(CID::class, $result);
        $this->assertSame(1, $result->version);
        $this->assertSame(0x71, $result->codec);
    }
}
