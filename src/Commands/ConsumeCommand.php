<?php

namespace SocialDept\Signal\Commands;

use Illuminate\Console\Command;
use SocialDept\Signal\Services\JetstreamConsumer;
use SocialDept\Signal\Services\SignalRegistry;

class ConsumeCommand extends Command
{
    protected $signature = 'signal:consume
                            {--cursor= : Start from a specific cursor position}
                            {--fresh : Start from the beginning, ignoring stored cursor}';

    protected $description = 'Start consuming events from the AT Protocol Jetstream';

    public function handle(JetstreamConsumer $consumer, SignalRegistry $registry): int
    {
        $this->info('Signal: Initializing Jetstream consumer...');

        // Discover signals
        $registry->discover();

        $signalCount = $registry->all()->count();
        $this->info("Registered {$signalCount} signal(s)");

        if ($signalCount === 0) {
            $this->warn('No signals registered. Create signals in app/Signals or register them in config/signal.php');
            return self::FAILURE;
        }

        // List registered signals
        $this->table(
            ['Signal', 'Event Types', 'Collections'],
            $registry->all()->map(function ($signal) {
                return [
                    get_class($signal),
                    implode(', ', $signal->eventTypes()),
                    $signal->collections() ? implode(', ', $signal->collections()) : 'All',
                ];
            })
        );

        // Determine cursor
        $cursor = null;
        if ($this->option('fresh')) {
            $this->info('Starting fresh from the beginning');
        } elseif ($this->option('cursor')) {
            $cursor = (int) $this->option('cursor');
            $this->info("Starting from cursor: {$cursor}");
        } else {
            $this->info('Resuming from stored cursor position');
        }

        // Start consuming
        $this->info('Starting Jetstream consumer... Press Ctrl+C to stop.');

        try {
            $consumer->start($cursor);
        } catch (\Exception $e) {
            $this->error('Error: ' . $e->getMessage());
            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
