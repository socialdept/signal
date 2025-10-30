<?php

namespace SocialDept\Signal\Events;

use SocialDept\Signal\Contracts\EventContract;

class AccountEvent implements EventContract
{
    public function __construct(
        public string $did,
        public bool $active,
        public ?string $status = null,
        public int $seq = 0,
        public ?string $time = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            did: $data['did'],
            active: $data['active'],
            status: $data['status'] ?? null,
            seq: $data['seq'] ?? 0,
            time: $data['time'] ?? null,
        );
    }

    public function toArray(): array
    {
        return [
            'did' => $this->did,
            'active' => $this->active,
            'status' => $this->status,
            'seq' => $this->seq,
            'time' => $this->time,
        ];
    }
}
