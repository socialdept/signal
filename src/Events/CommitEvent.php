<?php

namespace SocialDept\Signal\Events;

class CommitEvent
{
    public function __construct(
        public string $rev,
        public string $operation, // 'create', 'update', 'delete'
        public string $collection,
        public string $rkey,
        public ?object $record = null,
        public ?string $cid = null,
    ) {}

    public function isCreate(): bool
    {
        return $this->operation === 'create';
    }

    public function isUpdate(): bool
    {
        return $this->operation === 'update';
    }

    public function isDelete(): bool
    {
        return $this->operation === 'delete';
    }

    public function uri(): string
    {
        return "at://{$this->collection}/{$this->rkey}";
    }

    public static function fromArray(array $data): self
    {
        return new self(
            rev: $data['rev'],
            operation: $data['operation'],
            collection: $data['collection'],
            rkey: $data['rkey'],
            record: isset($data['record']) ? (object) $data['record'] : null,
            cid: $data['cid'] ?? null,
        );
    }

    public function toArray(): array
    {
        return [
            'rev' => $this->rev,
            'operation' => $this->operation,
            'collection' => $this->collection,
            'rkey' => $this->rkey,
            'record' => $this->record,
            'cid' => $this->cid,
        ];
    }
}
