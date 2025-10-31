<?php

declare(strict_types=1);

namespace SocialDept\Signals\CAR;

use Generator;
use SocialDept\Signals\Core\CBOR;
use SocialDept\Signals\Core\CID;

/**
 * Extract records from AT Protocol MST (Merkle Search Tree) blocks.
 *
 * Walks MST structure to extract collection/rkey records with their values.
 */
class RecordExtractor
{
    /**
     * @param array<string, string> $blocks Map of CID string => block data
     */
    public function __construct(
        private readonly array $blocks,
        private readonly string $did,
    ) {
    }

    /**
     * Extract all records from blocks.
     *
     * Yields records in format: "collection/rkey" => record data
     *
     * @return Generator<string, array>
     */
    public function extractRecords(CID $rootCid): Generator
    {
        yield from $this->walkTree($rootCid, '');
    }

    /**
     * Recursively walk MST tree.
     *
     * @param CID $cid Current node CID
     * @param string $prefix Path prefix accumulated from parent nodes
     * @return Generator<string, array>
     */
    private function walkTree(CID $cid, string $prefix): Generator
    {
        $cidStr = $cid->toString();

        // Get block data
        if (! isset($this->blocks[$cidStr])) {
            // Block not found - might be a pruned tree, skip it
            return;
        }

        $blockData = $this->blocks[$cidStr];

        // Decode CBOR block
        $node = CBOR::decode($blockData);

        if (! is_array($node)) {
            return;
        }

        // Process left subtree if exists
        if (isset($node['l']) && $node['l'] instanceof CID) {
            yield from $this->walkTree($node['l'], $prefix);
        }

        // Process entries
        if (isset($node['e']) && is_array($node['e'])) {
            foreach ($node['e'] as $entry) {
                if (! is_array($entry)) {
                    continue;
                }

                // Build full key from prefix + entry key
                $entryPrefix = $entry['p'] ?? 0;
                $keyPart = $entry['k'] ?? '';
                $fullKey = substr($prefix, 0, $entryPrefix) . $keyPart;

                // If entry has a tree link, walk it
                if (isset($entry['t']) && $entry['t'] instanceof CID) {
                    yield from $this->walkTree($entry['t'], $fullKey);
                }

                // If entry has a value (record), yield it
                if (isset($entry['v']) && $entry['v'] instanceof CID) {
                    $recordCid = $entry['v'];
                    $record = $this->getRecord($recordCid);

                    if ($record !== null) {
                        // Parse collection/rkey from key
                        $parts = explode('/', $fullKey, 2);
                        if (count($parts) === 2) {
                            [$collection, $rkey] = $parts;
                            $path = "{$collection}/{$rkey}";

                            yield $path => [
                                'uri' => "at://{$this->did}/{$path}",
                                'cid' => $recordCid->toString(),
                                'value' => $record,
                            ];
                        } else {
                            // Debug: log when key format doesn't match expected pattern
                            \Illuminate\Support\Facades\Log::debug('Signal: MST key parse failed', [
                                'fullKey' => $fullKey,
                                'parts' => $parts,
                                'did' => $this->did,
                            ]);
                        }
                    }
                }
            }
        }
    }

    /**
     * Get record data from block.
     */
    private function getRecord(CID $cid): ?array
    {
        $cidStr = $cid->toString();

        if (! isset($this->blocks[$cidStr])) {
            return null;
        }

        $data = CBOR::decode($this->blocks[$cidStr]);

        return is_array($data) ? $data : null;
    }
}
