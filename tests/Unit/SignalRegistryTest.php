<?php

namespace SocialDept\Signal\Tests\Unit;

use Orchestra\Testbench\TestCase;
use SocialDept\Signal\Events\CommitEvent;
use SocialDept\Signal\Events\JetstreamEvent;
use SocialDept\Signal\Services\SignalRegistry;
use SocialDept\Signal\Signals\Signal;

class SignalRegistryTest extends TestCase
{
    /** @test */
    public function it_matches_exact_collections()
    {
        $registry = new SignalRegistry();

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

        $result = $this->invokeMethod(
            $registry,
            'matchesCollection',
            ['app.bsky.feed.post', ['app.bsky.feed.post']]
        );

        $this->assertTrue($result);
    }

    /** @test */
    public function it_matches_wildcard_collections()
    {
        $registry = new SignalRegistry();

        // Test app.bsky.feed.*
        $this->assertTrue(
            $this->invokeMethod(
                $registry,
                'matchesCollection',
                ['app.bsky.feed.post', ['app.bsky.feed.*']]
            )
        );

        $this->assertTrue(
            $this->invokeMethod(
                $registry,
                'matchesCollection',
                ['app.bsky.feed.like', ['app.bsky.feed.*']]
            )
        );

        $this->assertFalse(
            $this->invokeMethod(
                $registry,
                'matchesCollection',
                ['app.bsky.graph.follow', ['app.bsky.feed.*']]
            )
        );

        // Test app.bsky.*
        $this->assertTrue(
            $this->invokeMethod(
                $registry,
                'matchesCollection',
                ['app.bsky.feed.post', ['app.bsky.*']]
            )
        );

        $this->assertTrue(
            $this->invokeMethod(
                $registry,
                'matchesCollection',
                ['app.bsky.graph.follow', ['app.bsky.*']]
            )
        );
    }

    /** @test */
    public function it_matches_multiple_patterns()
    {
        $registry = new SignalRegistry();

        $patterns = [
            'app.bsky.feed.post',
            'app.bsky.graph.*',
        ];

        // Exact match
        $this->assertTrue(
            $this->invokeMethod(
                $registry,
                'matchesCollection',
                ['app.bsky.feed.post', $patterns]
            )
        );

        // Wildcard match
        $this->assertTrue(
            $this->invokeMethod(
                $registry,
                'matchesCollection',
                ['app.bsky.graph.follow', $patterns]
            )
        );

        // No match
        $this->assertFalse(
            $this->invokeMethod(
                $registry,
                'matchesCollection',
                ['app.bsky.feed.like', $patterns]
            )
        );
    }

    /**
     * Call protected/private method of a class.
     */
    protected function invokeMethod(&$object, $methodName, array $parameters = [])
    {
        $reflection = new \ReflectionClass(get_class($object));
        $method = $reflection->getMethod($methodName);
        $method->setAccessible(true);

        return $method->invokeArgs($object, $parameters);
    }
}
