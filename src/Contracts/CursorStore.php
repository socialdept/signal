<?php

namespace SocialDept\Signal\Contracts;

interface CursorStore
{
    /**
     * Get the current cursor position.
     */
    public function get(): ?int;

    /**
     * Set the cursor position.
     */
    public function set(int $cursor): void;

    /**
     * Clear the cursor position.
     */
    public function clear(): void;
}
