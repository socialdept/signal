<?php

namespace SocialDept\Signal\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use SocialDept\Signal\Services\SignalRegistry;

class ListSignalsCommand extends Command
{
    protected $signature = 'signal:list';

    protected $description = 'List all registered Signals';

    public function handle(SignalRegistry $registry): int
    {
        $signals = $this->discoverSignals($registry);

        if ($signals->isEmpty()) {
            $this->displayNoSignalsWarning();

            return self::SUCCESS;
        }

        $this->displaySignalCount($signals->count());

        foreach ($signals as $signal) {
            $this->displaySignalDetails($signal);
        }

        return self::SUCCESS;
    }

    private function discoverSignals(SignalRegistry $registry): Collection
    {
        $registry->discover();

        return $registry->all();
    }

    private function displayNoSignalsWarning(): void
    {
        $this->warn('No signals registered.');
        $this->info('Create signals in app/Signals or register them in config/signal.php');
    }

    private function displaySignalCount(int $count): void
    {
        $this->components->info("Found {$count} ".str('signal')->plural($count));
        $this->newLine();
    }

    private function displaySignalDetails(object $signal): void
    {
        $className = class_basename($signal);
        $fullClassName = get_class($signal);

        $this->line("  <fg=green>•</> <options=bold>{$className}</>");
        $this->line("    <fg=gray>Class:</> {$fullClassName}");

        $this->displayEventTypes($signal);
        $this->displayCollections($signal);
        $this->displayDids($signal);
        $this->displayQueueInfo($signal);

        $this->newLine();
    }

    private function displayEventTypes(object $signal): void
    {
        $eventTypes = collect($signal->eventTypes())
            ->map(fn ($type) => "<fg=cyan>{$type}</>")
            ->join(', ');

        $this->line("    <fg=gray>Events:</> {$eventTypes}");
    }

    private function displayCollections(object $signal): void
    {
        $collections = $signal->collections()
            ? collect($signal->collections())->map(fn ($col) => "<fg=yellow>{$col}</>")->join(', ')
            : '<fg=gray>All collections</>';

        $this->line("    <fg=gray>Collections:</> {$collections}");
    }

    private function displayDids(object $signal): void
    {
        if (! $signal->dids()) {
            return;
        }

        $dids = collect($signal->dids())
            ->map(fn ($did) => "<fg=magenta>{$did}</>")
            ->join(', ');

        $this->line("    <fg=gray>DIDs:</> {$dids}");
    }

    private function displayQueueInfo(object $signal): void
    {
        if (! $signal->shouldQueue()) {
            return;
        }

        $queue = $signal->queue() ?? 'default';

        $this->line("    <fg=gray>Queue:</> <fg=blue>{$queue}</>");
    }
}
