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

        $this->components->info("Found {$signals->count()} " . str('signal')->plural($signals->count()));
        $this->newLine();

        foreach ($signals as $signal) {
            $className = class_basename($signal);
            $fullClassName = get_class($signal);

            $this->line("  <fg=green>•</> <options=bold>{$className}</>");
            $this->line("    <fg=gray>Class:</> {$fullClassName}");

            $eventTypes = collect($signal->eventTypes())->map(fn($type) => "<fg=cyan>{$type}</>")->join(', ');
            $this->line("    <fg=gray>Events:</> {$eventTypes}");

            $collections = $signal->collections()
                ? collect($signal->collections())->map(fn($col) => "<fg=yellow>{$col}</>")->join(', ')
                : '<fg=gray>All collections</>';
            $this->line("    <fg=gray>Collections:</> {$collections}");

            if ($signal->dids()) {
                $dids = collect($signal->dids())->map(fn($did) => "<fg=magenta>{$did}</>")->join(', ');
                $this->line("    <fg=gray>DIDs:</> {$dids}");
            }

            if ($signal->shouldQueue()) {
                $queue = $signal->queue() ?? 'default';
                $this->line("    <fg=gray>Queue:</> <fg=blue>{$queue}</>");
            }

            $this->newLine();
        }

        return self::SUCCESS;
    }
}
