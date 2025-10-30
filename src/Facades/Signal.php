<?php

namespace SocialDept\Signal\Facades;

use Illuminate\Support\Facades\Facade;
use SocialDept\Signal\Services\JetstreamConsumer;

/**
 * @method static void start(?int $cursor = null)
 * @method static void stop()
 *
 * @see \SocialDept\Signal\Services\JetstreamConsumer
 */
class Signal extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return JetstreamConsumer::class;
    }
}
