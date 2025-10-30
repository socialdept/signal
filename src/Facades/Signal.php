<?php

namespace SocialDept\Signal\Facades;

use Illuminate\Support\Facades\Facade;

class Signal extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor(): string
    {
        return 'signal';
    }
}
