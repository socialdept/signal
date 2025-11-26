<?php

namespace SocialDept\AtpSignals\Facades;

use Illuminate\Support\Facades\Facade;
use SocialDept\AtpSignals\Services\SignalManager;

/**
 * @method static void start(?int $cursor = null)
 * @method static void stop()
 * @method static string getMode()
 *
 * @see \SocialDept\AtpSignals\Services\SignalManager
 */
class Signal extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return SignalManager::class;
    }
}
