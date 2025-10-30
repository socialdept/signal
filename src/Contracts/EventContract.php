<?php

namespace SocialDept\Signal\Contracts;

interface EventContract
{
    /**
     * Create an instance from an array.
     */
    public static function fromArray(array $data): self;

    /**
     * Convert the instance to an array.
     */
    public function toArray(): array;
}
