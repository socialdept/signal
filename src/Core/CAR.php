<?php

declare(strict_types=1);

namespace SocialDept\AtpSignals\Core;

use SocialDept\AtpSignals\CAR\BlockReader;

/**
 * CAR (Content Addressable aRchive) facade.
 *
 * Provides static methods for parsing CAR data from AT Protocol Firehose.
 */
class CAR
{
    /**
     * Parse CAR blocks.
     *
     * Returns array of blocks keyed by CID string.
     * The blocks contain raw CBOR data, not decoded.
     *
     * @param string $data Binary CAR data
     * @param string|null $did DID for constructing URIs (not used, kept for compatibility)
     * @return array<string, string> Map of CID string => block data
     */
    public static function blockMap(string $data, ?string $did = null): array
    {
        // Read all blocks from CAR
        $blockReader = new BlockReader($data);

        return $blockReader->getBlockMap();
    }

    /**
     * Extract DID from commit block.
     */
    private static function extractDidFromBlocks(array $blocks): ?string
    {
        // The first block is typically the commit
        $firstBlock = reset($blocks);

        if ($firstBlock === false) {
            return null;
        }

        $decoded = CBOR::decode($firstBlock);

        if (! is_array($decoded)) {
            return null;
        }

        return $decoded['did'] ?? null;
    }

    /**
     * Find MST root CID from blocks.
     */
    private static function findMstRoot(array $blocks, array $cids): ?CID
    {
        // Try to parse commit block to get data CID
        $firstBlock = reset($blocks);

        if ($firstBlock === false) {
            return null;
        }

        $commit = CBOR::decode($firstBlock);

        if (! is_array($commit)) {
            return null;
        }

        // MST root is in the 'data' field of commit
        if (isset($commit['data']) && $commit['data'] instanceof CID) {
            return $commit['data'];
        }

        // Fallback: second block is often the MST root
        if (count($cids) >= 2) {
            return CID::fromString($cids[1]);
        }

        return null;
    }
}
