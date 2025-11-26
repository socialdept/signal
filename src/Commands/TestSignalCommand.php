<?php

namespace SocialDept\AtpSignals\Commands;

use Illuminate\Console\Command;
use InvalidArgumentException;
use SocialDept\AtpSignals\Events\CommitEvent;
use SocialDept\AtpSignals\Events\SignalEvent;

class TestSignalCommand extends Command
{
    protected $signature = 'signal:test
                            {signal : The Signal class name}
                            {--sample=commit : The type of sample event to use}';

    protected $description = 'Test a Signal with sample data';

    public function handle(): int
    {
        $signalClass = $this->resolveSignalClass();

        if ($signalClass === null) {
            return self::FAILURE;
        }

        $signal = app($signalClass);

        $this->displayTestHeader($signalClass);

        $event = $this->createAndDisplaySampleEvent();

        return $this->executeSignal($signal, $event);
    }

    private function resolveSignalClass(): ?string
    {
        $signalClass = $this->argument('signal');

        if (! class_exists($signalClass)) {
            $signalClass = 'App\\Signals\\'.$signalClass;
        }

        if (! class_exists($signalClass)) {
            $this->error("Signal class not found: {$signalClass}");

            return null;
        }

        return $signalClass;
    }

    private function displayTestHeader(string $signalClass): void
    {
        $this->info("Testing signal: {$signalClass}");
        $this->newLine();
    }

    private function createAndDisplaySampleEvent(): SignalEvent
    {
        $event = $this->createSampleEvent($this->option('sample'));

        $this->info('Sample event created:');
        $this->line(json_encode($event->toArray(), JSON_PRETTY_PRINT));
        $this->newLine();

        return $event;
    }

    private function executeSignal(object $signal, SignalEvent $event): int
    {
        try {
            if ($signal->shouldHandle($event)) {
                $this->info('Calling signal->handle()...');
                $signal->handle($event);
                $this->info('✓ Signal executed successfully');
            } else {
                $this->warn('Signal->shouldHandle() returned false');
            }
        } catch (\Exception $e) {
            $this->error('Error executing signal: '.$e->getMessage());

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    protected function createSampleEvent(string $type): SignalEvent
    {
        return match ($type) {
            'commit' => new SignalEvent(
                did: 'did:plc:sample123456789',
                timeUs: time() * 1000000,
                kind: 'commit',
                commit: new CommitEvent(
                    rev: 'sample-rev',
                    operation: 'create',
                    collection: 'app.bsky.feed.post',
                    rkey: 'sample-rkey',
                    record: (object) [
                        'text' => 'This is a sample post for testing',
                        'createdAt' => now()->toIso8601String(),
                    ],
                    cid: 'sample-cid',
                ),
            ),
            default => throw new InvalidArgumentException("Unknown sample type: {$type}"),
        };
    }
}
