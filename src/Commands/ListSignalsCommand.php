<?php

namespace SocialDept\Signal\Commands;

use Illuminate\Console\Command;
use SocialDept\Signal\Services\SignalRegistry;

class ListSignalsCommand extends Command
{
    protected $signature = 'signal:list';

    protected $description = 'List all registered Signals';

    public function handle(SignalRegistry $registry): int
    {
        $registry->discover();

        $signals = $registry->all();

        if ($signals->isEmpty()) {
            $this->warn('No signals registered.');
            $this->info('Create signals in app/Signals or register them in config/signal.php');
            return self::SUCCESS;
        }

        $this->info("Found {$signals->count()} signal(s):");
        $this->newLine();

        $this->table(
            ['Signal', 'Event Types', 'Collections', 'DIDs', 'Queued'],
            $signals->map(function ($signal) {
                return [
                    get_class($signal),
                    implode(', ', $signal->eventTypes()),
                    $signal->collections() ? implode(', ', $signal->collections()) : 'All',
                    $signal->dids() ? implode(', ', $signal->dids()) : 'All',
                    $signal->shouldQueue() ? 'Yes' : 'No',
                ];
            })
        );

        return self::SUCCESS;
    }
}
