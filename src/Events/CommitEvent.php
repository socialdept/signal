<?php

namespace SocialDept\Signal\Events;

use SocialDept\Signal\Contracts\EventContract;
use SocialDept\Signal\Enums\SignalCommitOperation;

class CommitEvent implements EventContract
{
    public SignalCommitOperation $operation;

    public function __construct(
        public string $rev,
        string|SignalCommitOperation $operation,
        public string $collection,
        public string $rkey,
        public ?object $record = null,
        public ?string $cid = null,
    ) {
        // Convert string to enum if needed
        $this->operation = is_string($operation)
            ? SignalCommitOperation::from($operation)
            : $operation;
    }

    public function isCreate(): bool
    {
        return $this->operation === SignalCommitOperation::Create;
    }

    public function isUpdate(): bool
    {
        return $this->operation === SignalCommitOperation::Update;
    }

    public function isDelete(): bool
    {
        return $this->operation === SignalCommitOperation::Delete;
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
            'operation' => $this->operation->value,
            'collection' => $this->collection,
            'rkey' => $this->rkey,
            'record' => $this->record,
            'cid' => $this->cid,
        ];
    }
}
