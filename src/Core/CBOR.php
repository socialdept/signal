<?php

declare(strict_types=1);

namespace SocialDept\AtpSignals\Core;

use SocialDept\AtpSignals\CBOR\Decoder;

/**
 * CBOR facade for simple decoding operations.
 *
 * Provides static methods matching the interface needed by FirehoseConsumer.
 */
class CBOR
{
    /**
     * Decode first CBOR item and return remainder.
     *
     * @param string $data Binary CBOR data
     * @return array{0: mixed, 1: string} [decoded value, remaining data]
     */
    public static function decodeFirst(string $data): array
    {
        $decoder = new Decoder($data);
        $value = $decoder->decode();

        // Calculate remaining data based on decoder position
        $position = $decoder->getPosition();
        $remainder = substr($data, $position);

        return [$value, $remainder];
    }

    /**
     * Decode complete CBOR data.
     *
     * @param string $data Binary CBOR data
     * @return mixed Decoded value
     */
    public static function decode(string $data): mixed
    {
        $decoder = new Decoder($data);

        return $decoder->decode();
    }

    /**
     * Decode all CBOR items from data.
     *
     * @param string $data Binary CBOR data
     * @return array All decoded values
     */
    public static function decodeAll(string $data): array
    {
        $decoder = new Decoder($data);
        $items = [];

        while ($decoder->hasMore()) {
            $items[] = $decoder->decode();
        }

        return $items;
    }
}
