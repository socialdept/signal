<?php

namespace SocialDept\Signal\Storage;

use Illuminate\Support\Facades\File;
use SocialDept\Signal\Contracts\CursorStore;

class FileCursorStore implements CursorStore
{
    protected string $path;

    public function __construct()
    {
        $this->path = config('signal.cursor_config.file.path', storage_path('signal/cursor.json'));

        // Ensure directory exists
        $directory = dirname($this->path);
        if (! File::exists($directory)) {
            File::makeDirectory($directory, 0755, true);
        }
    }

    public function get(): ?int
    {
        if (! File::exists($this->path)) {
            return null;
        }

        $data = json_decode(File::get($this->path), true);

        return $data['cursor'] ?? null;
    }

    public function set(int $cursor): void
    {
        File::put($this->path, json_encode([
            'cursor' => $cursor,
            'updated_at' => now()->toIso8601String(),
        ], JSON_PRETTY_PRINT));
    }

    public function clear(): void
    {
        if (File::exists($this->path)) {
            File::delete($this->path);
        }
    }
}
