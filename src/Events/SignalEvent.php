<?php

namespace SocialDept\Signal\Events;

use SocialDept\Signal\Contracts\EventContract;

class SignalEvent implements EventContract
{
    public function __construct(
        public string $did,
        public int $timeUs,
        public string $kind, // 'commit', 'identity', 'account'
        public ?CommitEvent $commit = null,
        public ?IdentityEvent $identity = null,
        public ?AccountEvent $account = null,
    ) {
    }

    public function isCommit(): bool
    {
        return $this->kind === 'commit';
    }

    public function isIdentity(): bool
    {
        return $this->kind === 'identity';
    }

    public function isAccount(): bool
    {
        return $this->kind === 'account';
    }

    public function getCollection(): ?string
    {
        return $this->commit?->collection;
    }

    public function getRecord(): ?object
    {
        return $this->commit?->record;
    }

    public function getOperation(): ?\SocialDept\Signal\Enums\SignalCommitOperation
    {
        return $this->commit?->operation;
    }

    public static function fromArray(array $data): self
    {
        $commit = isset($data['commit'])
            ? CommitEvent::fromArray($data['commit'])
            : null;

        $identity = isset($data['identity'])
            ? IdentityEvent::fromArray($data['identity'])
            : null;

        $account = isset($data['account'])
            ? AccountEvent::fromArray($data['account'])
            : null;

        return new self(
            did: $data['did'],
            timeUs: $data['time_us'],
            kind: $data['kind'],
            commit: $commit,
            identity: $identity,
            account: $account,
        );
    }

    public function toArray(): array
    {
        return [
            'did' => $this->did,
            'time_us' => $this->timeUs,
            'kind' => $this->kind,
            'commit' => $this->commit?->toArray(),
            'identity' => $this->identity?->toArray(),
            'account' => $this->account?->toArray(),
        ];
    }
}
