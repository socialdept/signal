<?php

namespace SocialDept\Signal\Commands;

use Illuminate\Console\Command;
use SocialDept\Signal\Events\JetstreamEvent;
use SocialDept\Signal\Events\CommitEvent;

class TestSignalCommand extends Command
{
    protected $signature = 'signal:test
                            {signal : The Signal class name}
                            {--sample=commit : The type of sample event to use}';

    protected $description = 'Test a Signal with sample data';

    public function handle(): int
    {
        $signalClass = $this->argument('signal');

        // Try to resolve the class
        if (!class_exists($signalClass)) {
            $signalClass = 'App\\Signals\\' . $signalClass;
        }

        if (!class_exists($signalClass)) {
            $this->error("Signal class not found: {$signalClass}");
            return self::FAILURE;
        }

        $signal = app($signalClass);

        $this->info("Testing signal: {$signalClass}");
        $this->newLine();

        // Create sample event
        $event = $this->createSampleEvent($this->option('sample'));

        $this->info('Sample event created:');
        $this->line(json_encode($event->toArray(), JSON_PRETTY_PRINT));
        $this->newLine();

        try {
            if ($signal->shouldHandle($event)) {
                $this->info('Calling signal->handle()...');
                $signal->handle($event);
                $this->info('✓ Signal executed successfully');
            } else {
                $this->warn('Signal->shouldHandle() returned false');
            }
        } catch (\Exception $e) {
            $this->error('Error executing signal: ' . $e->getMessage());
            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    protected function createSampleEvent(string $type): JetstreamEvent
    {
        switch ($type) {
            case 'commit':
                return new JetstreamEvent(
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
                );

            default:
                throw new \InvalidArgumentException("Unknown sample type: {$type}");
        }
    }
}
