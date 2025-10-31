<?php

declare(strict_types=1);

namespace SocialDept\Signal\Tests\Unit;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use SocialDept\Signal\Binary\Varint;

class VarintTest extends TestCase
{
    public function test_decode_single_byte_values(): void
    {
        $this->assertSame(0, Varint::decode("\x00"));
        $this->assertSame(1, Varint::decode("\x01"));
        $this->assertSame(127, Varint::decode("\x7F"));
    }

    public function test_decode_multi_byte_values(): void
    {
        // 128 = 0x80 0x01
        $this->assertSame(128, Varint::decode("\x80\x01"));

        // 300 = 0xAC 0x02
        $this->assertSame(300, Varint::decode("\xAC\x02"));

        // 16384 = 0x80 0x80 0x01
        $this->assertSame(16384, Varint::decode("\x80\x80\x01"));
    }

    public function test_decode_with_offset(): void
    {
        $data = "\x00\x01\x7F\x80\x01";
        $offset = 0;

        $this->assertSame(0, Varint::decode($data, $offset));
        $this->assertSame(1, $offset);

        $this->assertSame(1, Varint::decode($data, $offset));
        $this->assertSame(2, $offset);

        $this->assertSame(127, Varint::decode($data, $offset));
        $this->assertSame(3, $offset);

        $this->assertSame(128, Varint::decode($data, $offset));
        $this->assertSame(5, $offset);
    }

    public function test_decode_first_returns_value_and_remainder(): void
    {
        [$value, $remainder] = Varint::decodeFirst("\x7F\x01\x02\x03");

        $this->assertSame(127, $value);
        $this->assertSame("\x01\x02\x03", $remainder);
    }

    public function test_decode_throws_on_unexpected_end(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Unexpected end of varint data');

        Varint::decode("\x80");
    }

    public function test_decode_throws_on_too_long_varint(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Varint too long (max 64 bits)');

        // Create a varint that would be longer than 64 bits (10 bytes with continuation bits)
        $tooLong = str_repeat("\xFF", 10);
        Varint::decode($tooLong);
    }
}
