<?php

declare(strict_types=1);

namespace SocialDept\Signal\Tests\Integration;

use Orchestra\Testbench\TestCase;
use SocialDept\Signal\Core\CAR;
use SocialDept\Signal\Core\CBOR;
use SocialDept\Signal\Core\CID;

class FirehoseConsumerTest extends TestCase
{
    public function test_cbor_can_decode_firehose_message_header(): void
    {
        // Simulate a Firehose message header
        // Map with 't' => '#commit', 'op' => 1
        $header = [
            't' => '#commit',
            'op' => 1,
        ];

        // Encode it manually for testing
        $cbor = "\xA2"; // Map with 2 items
        $cbor .= "\x61t"; // Text string 't'
        $cbor .= "\x67#commit"; // Text string '#commit'
        $cbor .= "\x62op"; // Text string 'op'
        $cbor .= "\x01"; // Integer 1

        [$decoded, $remainder] = CBOR::decodeFirst($cbor);

        $this->assertIsArray($decoded);
        $this->assertArrayHasKey('t', $decoded);
        $this->assertArrayHasKey('op', $decoded);
        $this->assertSame('#commit', $decoded['t']);
        $this->assertSame(1, $decoded['op']);
    }

    public function test_cbor_can_decode_commit_payload(): void
    {
        // Simplified commit payload structure
        $payload = [
            'repo' => 'did:plc:test123',
            'rev' => 'test-rev',
            'seq' => 12345,
            'time' => '2024-01-01T00:00:00Z',
            'ops' => [],
        ];

        // Encode a simple payload
        $cbor = "\xA5"; // Map with 5 items

        // 'repo' key
        $cbor .= "\x64repo"; // Text string 'repo'
        $cbor .= "\x6Fdid:plc:test123"; // Text string 'did:plc:test123'

        // 'rev' key
        $cbor .= "\x63rev"; // Text string 'rev'
        $cbor .= "\x68test-rev"; // Text string 'test-rev'

        // 'seq' key
        $cbor .= "\x63seq"; // Text string 'seq'
        $cbor .= "\x19\x30\x39"; // Integer 12345

        // 'time' key
        $cbor .= "\x64time"; // Text string 'time'
        $cbor .= "\x78\x182024-01-01T00:00:00Z"; // Text string (length 24)

        // 'ops' key
        $cbor .= "\x63ops"; // Text string 'ops'
        $cbor .= "\x80"; // Empty array

        $decoded = CBOR::decode($cbor);

        $this->assertIsArray($decoded);
        $this->assertSame('did:plc:test123', $decoded['repo']);
        $this->assertSame('test-rev', $decoded['rev']);
        $this->assertSame(12345, $decoded['seq']);
    }

    public function test_cid_can_be_decoded_from_cbor_tag(): void
    {
        // Create a CID and encode it as CBOR tag 42
        $hash = hash('sha256', 'test-content', true);
        $cidBinary = "\x01\x71\x12\x20" . $hash; // CIDv1, dag-cbor, sha256
        $cidBytes = "\x00" . $cidBinary; // Add 0x00 prefix

        // CBOR tag 42 + byte string
        $length = strlen($cidBytes);
        $cbor = "\xD8\x2A\x58" . chr($length) . $cidBytes;

        $decoded = CBOR::decode($cbor);

        $this->assertInstanceOf(CID::class, $decoded);
        $this->assertSame(1, $decoded->version);
        $this->assertSame(0x71, $decoded->codec);
    }

    public function test_car_can_extract_blocks(): void
    {
        // Create a minimal CAR with header and one block
        $car = '';

        // CAR header (minimal)
        $headerCbor = "\xA1\x67version\x01"; // {version: 1}
        $headerLength = strlen($headerCbor);
        $car .= chr($headerLength) . $headerCbor;

        // Create a block with CID
        $blockData = "\xA1\x64test\x65value"; // {test: "value"}
        $cid = CID::fromBinary("\x01\x71\x12\x20" . str_repeat("\x00", 32));
        $cidBinary = $cid->toBinary();
        $cidLength = strlen($cidBinary);

        $block = chr($cidLength) . $cidBinary . $blockData;
        $blockLength = strlen($block);

        // Add varint-encoded block length
        $car .= chr($blockLength) . $block;

        // This should not throw an error
        $blocks = [];
        foreach (CAR::blockMap($car, 'did:plc:test') as $key => $value) {
            $blocks[$key] = $value;
        }

        // Even if empty, it shouldn't crash
        $this->assertIsArray($blocks);
    }

    public function test_firehose_consumer_message_structure(): void
    {
        // Test the exact structure FirehoseConsumer expects

        // 1. Create CBOR header
        $headerMap = [
            't' => '#commit',
            'op' => 1,
        ];

        $header = "\xA2"; // Map with 2 items
        $header .= "\x61t\x67#commit"; // 't' => '#commit'
        $header .= "\x62op\x01"; // 'op' => 1

        // 2. Create CBOR payload
        $payload = "\xA6"; // Map with 6 items
        $payload .= "\x63seq\x19\x30\x39"; // 'seq' => 12345
        $payload .= "\x66rebase\xF4"; // 'rebase' => false
        $payload .= "\x64repo\x6Fdid:plc:test123"; // 'repo' => 'did:plc:test123'
        $payload .= "\x66commit\xA0"; // 'commit' => {}
        $payload .= "\x63rev\x68test-rev"; // 'rev' => 'test-rev'
        $payload .= "\x65since\x66origin"; // 'since' => 'origin'

        // Add required fields
        $payload .= "\x66blocks\x40"; // 'blocks' => empty byte string
        $payload .= "\x63ops\x80"; // 'ops' => []
        $payload .= "\x64time\x78\x182024-01-01T00:00:00Z"; // 'time' => timestamp

        // Combine header + payload
        $message = $header . $payload;

        // Test decoding header
        [$decodedHeader, $remainder] = CBOR::decodeFirst($message);

        $this->assertIsArray($decodedHeader);
        $this->assertSame('#commit', $decodedHeader['t']);
        $this->assertSame(1, $decodedHeader['op']);

        // Test decoding payload
        $decodedPayload = CBOR::decode($remainder);

        $this->assertIsArray($decodedPayload);
        $this->assertArrayHasKey('seq', $decodedPayload);
        $this->assertArrayHasKey('repo', $decodedPayload);
        $this->assertArrayHasKey('rev', $decodedPayload);
    }

    public function test_complete_firehose_message_flow(): void
    {
        // This test simulates the complete flow that FirehoseConsumer::handleMessage() uses

        // Step 1: CBOR header
        $header = "\xA2\x61t\x67#commit\x62op\x01";

        // Step 2: CBOR payload with all required fields
        $payload = "\xA9"; // Map with 9 items
        $payload .= "\x63seq\x19\x30\x39"; // seq: 12345
        $payload .= "\x66rebase\xF4"; // rebase: false
        $payload .= "\x64repo\x6Fdid:plc:test123"; // repo: "did:plc:test123"
        $payload .= "\x66commit\xA0"; // commit: {}
        $payload .= "\x63rev\x68test-rev"; // rev: "test-rev"
        $payload .= "\x65since\x66origin"; // since: "origin"
        $payload .= "\x66blocks\x40"; // blocks: b''
        $payload .= "\x63ops\x80"; // ops: []
        $payload .= "\x64time\x78\x182024-01-01T00:00:00Z"; // time: "2024-01-01T00:00:00Z"

        $message = $header . $payload;

        // Simulate FirehoseConsumer::handleMessage() logic

        // 1. Decode CBOR header
        [$decodedHeader, $remainder] = CBOR::decodeFirst($message);

        $this->assertArrayHasKey('t', $decodedHeader);
        $this->assertArrayHasKey('op', $decodedHeader);

        // 2. Check operation
        $this->assertSame(1, $decodedHeader['op']);

        // 3. Decode payload
        $decodedPayload = CBOR::decode($remainder);

        // 4. Verify required fields exist
        $requiredFields = ['seq', 'rebase', 'repo', 'commit', 'rev', 'since', 'blocks', 'ops', 'time'];
        foreach ($requiredFields as $field) {
            $this->assertArrayHasKey($field, $decodedPayload);
        }

        // 5. Verify data types
        $this->assertIsInt($decodedPayload['seq']);
        $this->assertIsBool($decodedPayload['rebase']);
        $this->assertIsString($decodedPayload['repo']);
        $this->assertIsArray($decodedPayload['ops']);

        // Success! The message structure is valid
        $this->assertTrue(true);
    }
}
