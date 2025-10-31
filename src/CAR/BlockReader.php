<?php

declare(strict_types=1);

namespace SocialDept\Signal\CAR;

use Generator;
use SocialDept\Signal\Binary\Reader;
use SocialDept\Signal\Core\CID;
use SocialDept\Signal\Core\CBOR;

/**
 * CAR (Content Addressable aRchive) block reader.
 *
 * Reads blocks from CAR format data used in AT Protocol commits.
 */
class BlockReader
{
    private Reader $reader;

    public function __construct(string $data)
    {
        $this->reader = new Reader($data);
    }

    /**
     * Read all blocks from CAR data.
     *
     * Yields [CID, block data] pairs.
     *
     * @return Generator<array{0: CID, 1: string}>
     */
    public function blocks(): Generator
    {
        // Skip CAR header (we don't need it for Firehose processing)
        $this->skipHeader();

        // Read blocks until end of data
        while ($this->reader->hasMore()) {
            $block = $this->readBlock();
            if ($block !== null) {
                yield $block;
            }
        }
    }

    /**
     * Skip CAR header.
     */
    private function skipHeader(): void
    {
        if (!$this->reader->hasMore()) {
            return;
        }

        // Read header length (varint)
        $headerLength = $this->reader->readVarint();

        // Skip header data
        $this->reader->skip($headerLength);
    }

    /**
     * Read a single block.
     *
     * @return array{0: CID, 1: string}|null [CID, block data] or null if no more blocks
     */
    private function readBlock(): ?array
    {
        if (!$this->reader->hasMore()) {
            return null;
        }

        // Read block length (varint) - this is the total length of CID + data
        $blockLength = $this->reader->readVarint();

        if ($blockLength === 0) {
            return null;
        }

        // Read entire block data
        $blockData = $this->reader->readBytes($blockLength);

        // Parse CID from the beginning of block data
        // CIDs in CAR blocks are self-delimiting (no separate length prefix)
        // We need to parse the CID to find out its length
        $cidReader = new Reader($blockData);

        // Read CID version
        $version = $cidReader->readVarint();

        if ($version === 0x12) {
            // CIDv0 - multihash only (starting with 0x12 for SHA-256)
            $hashLength = $cidReader->readVarint();
            $cidReader->readBytes($hashLength); // Skip hash bytes
        } elseif ($version === 1) {
            // CIDv1 - version + codec + multihash
            $codec = $cidReader->readVarint();
            $hashType = $cidReader->readVarint();
            $hashLength = $cidReader->readVarint();
            $cidReader->readBytes($hashLength); // Skip hash bytes
        } else {
            throw new \RuntimeException("Unsupported CID version in CAR block: {$version}");
        }

        // Now we know the CID length
        $cidLength = $cidReader->getPosition();
        $cidBytes = substr($blockData, 0, $cidLength);
        $cid = CID::fromBinary($cidBytes);

        // Remaining data is the block content
        $content = substr($blockData, $cidLength);

        return [$cid, $content];
    }

    /**
     * Get all blocks as an associative array.
     *
     * @return array<string, string> Map of CID string => block data
     */
    public function getBlockMap(): array
    {
        $blocks = [];

        foreach ($this->blocks() as [$cid, $data]) {
            $cidString = $cid->toString();
            $blocks[$cidString] = $data;
        }

        return $blocks;
    }
}
