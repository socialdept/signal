<?php

namespace SocialDept\AtpSignals\Storage;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use SocialDept\AtpSignals\Contracts\CursorStore;

class DatabaseCursorStore implements CursorStore
{
    protected string $table;
    protected ?string $connection;

    public function __construct()
    {
        $this->table = config('atp-signals.cursor_config.database.table', 'signal_cursors');
        $this->connection = config('atp-signals.cursor_config.database.connection');
    }

    public function get(): ?int
    {
        $cursor = $this->query()
            ->where('key', 'jetstream')
            ->value('cursor');

        return $cursor ? (int) $cursor : null;
    }

    public function set(int $cursor): void
    {
        $this->query()
            ->updateOrInsert(
                ['key' => 'jetstream'],
                [
                    'cursor' => $cursor,
                    'updated_at' => now(),
                ]
            );
    }

    public function clear(): void
    {
        $this->query()
            ->where('key', 'jetstream')
            ->delete();
    }

    protected function query(): Builder
    {
        return DB::connection($this->connection)
            ->table($this->table);
    }
}
