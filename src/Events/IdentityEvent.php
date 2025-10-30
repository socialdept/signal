<?php

namespace SocialDept\Signal\Events;

class IdentityEvent
{
    public function __construct(
        public string $did,
        public ?string $handle = null,
        public int $seq = 0,
        public ?string $time = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            did: $data['did'],
            handle: $data['handle'] ?? null,
            seq: $data['seq'] ?? 0,
            time: $data['time'] ?? null,
        );
    }

    public function toArray(): array
    {
        return [
            'did' => $this->did,
            'handle' => $this->handle,
            'seq' => $this->seq,
            'time' => $this->time,
        ];
    }
}
