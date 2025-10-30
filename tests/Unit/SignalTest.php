<?php

namespace SocialDept\Signal\Tests\Unit;

use Orchestra\Testbench\TestCase;
use SocialDept\Signal\Events\CommitEvent;
use SocialDept\Signal\Events\JetstreamEvent;
use SocialDept\Signal\Signals\Signal;

class SignalTest extends TestCase
{
    /** @test */
    public function it_can_create_a_signal()
    {
        $signal = new class extends Signal {
            public function eventTypes(): array
            {
                return ['commit'];
            }

            public function handle(JetstreamEvent $event): void
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
        $signal = new class extends Signal {
            public function eventTypes(): array
            {
                return ['commit'];
            }

            public function collections(): ?array
            {
                return ['app.bsky.feed.post'];
            }

            public function handle(JetstreamEvent $event): void
            {
                //
            }
        };

        $event = new JetstreamEvent(
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
        $signal = new class extends Signal {
            public function eventTypes(): array
            {
                return ['commit'];
            }

            public function collections(): ?array
            {
                return ['app.bsky.feed.*'];
            }

            public function handle(JetstreamEvent $event): void
            {
                //
            }
        };

        // Test that it matches app.bsky.feed.post
        $postEvent = new JetstreamEvent(
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

        $this->assertTrue($signal->shouldHandle($postEvent));

        // Test that it matches app.bsky.feed.like
        $likeEvent = new JetstreamEvent(
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

        $this->assertTrue($signal->shouldHandle($likeEvent));

        // Test that it does NOT match app.bsky.graph.follow
        $followEvent = new JetstreamEvent(
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

        $this->assertFalse($signal->shouldHandle($followEvent));
    }
}
