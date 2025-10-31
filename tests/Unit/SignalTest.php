<?php

namespace SocialDept\Signals\Tests\Unit;

use Orchestra\Testbench\TestCase;
use SocialDept\Signals\Events\CommitEvent;
use SocialDept\Signals\Events\SignalEvent;
use SocialDept\Signals\Signals\Signal;

class SignalTest extends TestCase
{
    /** @test */
    public function it_can_create_a_signal()
    {
        $signal = new class () extends Signal {
            public function eventTypes(): array
            {
                return ['commit'];
            }

            public function handle(SignalEvent $event): void
            {
                //
            }
        };

        $this->assertInstanceOf(Signal::class, $signal);
        $this->assertEquals(['commit'], $signal->eventTypes());
    }

    /** @test */
    public function it_can_filter_by_exact_collection()
    {
        $signal = new class () extends Signal {
            public function eventTypes(): array
            {
                return ['commit'];
            }

            public function collections(): ?array
            {
                return ['app.bsky.feed.post'];
            }

            public function handle(SignalEvent $event): void
            {
                //
            }
        };

        $event = new SignalEvent(
            did: 'did:plc:test',
            timeUs: time() * 1000000,
            kind: 'commit',
            commit: new CommitEvent(
                rev: 'test',
                operation: 'create',
                collection: 'app.bsky.feed.post',
                rkey: 'test',
            ),
        );

        $this->assertTrue($signal->shouldHandle($event));
    }

    /** @test */
    public function it_can_filter_by_wildcard_collection()
    {
        $signalClass = new class () extends Signal {
            public function eventTypes(): array
            {
                return ['commit'];
            }

            public function collections(): ?array
            {
                return ['app.bsky.feed.*'];
            }

            public function handle(SignalEvent $event): void
            {
                //
            }
        };

        // Create registry and register the signal
        $registry = new \SocialDept\Signals\Services\SignalRegistry();
        $registry->register($signalClass::class);

        // Test that it matches app.bsky.feed.post
        $postEvent = new SignalEvent(
            did: 'did:plc:test',
            timeUs: time() * 1000000,
            kind: 'commit',
            commit: new CommitEvent(
                rev: 'test',
                operation: 'create',
                collection: 'app.bsky.feed.post',
                rkey: 'test',
            ),
        );

        $matchingSignals = $registry->getMatchingSignals($postEvent);
        $this->assertCount(1, $matchingSignals);

        // Test that it matches app.bsky.feed.like
        $likeEvent = new SignalEvent(
            did: 'did:plc:test',
            timeUs: time() * 1000000,
            kind: 'commit',
            commit: new CommitEvent(
                rev: 'test',
                operation: 'create',
                collection: 'app.bsky.feed.like',
                rkey: 'test',
            ),
        );

        $matchingSignals = $registry->getMatchingSignals($likeEvent);
        $this->assertCount(1, $matchingSignals);

        // Test that it does NOT match app.bsky.graph.follow
        $followEvent = new SignalEvent(
            did: 'did:plc:test',
            timeUs: time() * 1000000,
            kind: 'commit',
            commit: new CommitEvent(
                rev: 'test',
                operation: 'create',
                collection: 'app.bsky.graph.follow',
                rkey: 'test',
            ),
        );

        $matchingSignals = $registry->getMatchingSignals($followEvent);
        $this->assertCount(0, $matchingSignals);
    }
}
