<?php

declare(strict_types=1);

namespace SocialDept\Signal\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SocialDept\Signal\Core\CID;

class CIDTest extends TestCase
{
    public function test_parse_binary_cidv1(): void
    {
        // CIDv1: version=1, codec=0x71 (dag-cbor), sha256 hash
        $hash = str_repeat("\x00", 32);
        $binary = "\x01\x71\x12\x20" . $hash;

        $cid = CID::fromBinary($binary);

        $this->assertSame(1, $cid->version);
        $this->assertSame(0x71, $cid->codec);
        $this->assertSame("\x12\x20" . $hash, $cid->hash);
    }

    public function test_parse_binary_cidv0(): void
    {
        // CIDv0: starts with 0x12 (sha256) 0x20 (32 bytes)
        $hash = str_repeat("\x00", 32);
        $binary = "\x12\x20" . $hash;

        $cid = CID::fromBinary($binary);

        $this->assertSame(0, $cid->version);
        $this->assertSame(0x70, $cid->codec); // dag-pb
        $this->assertSame("\x12\x20" . $hash, $cid->hash);
    }

    public function test_to_string_cidv1(): void
    {
        $hash = str_repeat("\x00", 32);
        $binary = "\x01\x71\x12\x20" . $hash;
        $cid = CID::fromBinary($binary);

        $str = $cid->toString();

        // Should start with 'b' (base32 prefix)
        $this->assertStringStartsWith('b', $str);

        // Should be able to parse it back
        $parsed = CID::fromString($str);
        $this->assertSame($cid->version, $parsed->version);
        $this->assertSame($cid->codec, $parsed->codec);
    }

    public function test_to_binary_cidv1(): void
    {
        $hash = str_repeat("\x00", 32);
        $binary = "\x01\x71\x12\x20" . $hash;
        $cid = CID::fromBinary($binary);

        $this->assertSame($binary, $cid->toBinary());
    }

    public function test_round_trip_binary(): void
    {
        $hash = hash('sha256', 'test', true);
        $binary = "\x01\x71\x12\x20" . $hash;

        $cid = CID::fromBinary($binary);
        $encoded = $cid->toBinary();
        $decoded = CID::fromBinary($encoded);

        $this->assertSame($cid->version, $decoded->version);
        $this->assertSame($cid->codec, $decoded->codec);
        $this->assertSame($cid->hash, $decoded->hash);
    }

    public function test_round_trip_string(): void
    {
        $hash = hash('sha256', 'test', true);
        $binary = "\x01\x71\x12\x20" . $hash;
        $cid = CID::fromBinary($binary);

        $str = $cid->toString();
        $parsed = CID::fromString($str);

        $this->assertSame($cid->version, $parsed->version);
        $this->assertSame($cid->codec, $parsed->codec);
        $this->assertSame($cid->hash, $parsed->hash);
    }

    public function test_to_string_magic_method(): void
    {
        $hash = str_repeat("\x00", 32);
        $binary = "\x01\x71\x12\x20" . $hash;
        $cid = CID::fromBinary($binary);

        $this->assertSame($cid->toString(), (string) $cid);
    }
}
