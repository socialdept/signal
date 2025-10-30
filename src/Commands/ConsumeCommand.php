<?php

namespace SocialDept\Signal\Commands;

use Illuminate\Console\Command;
use SocialDept\Signal\Services\FirehoseConsumer;
use SocialDept\Signal\Services\JetstreamConsumer;
use SocialDept\Signal\Services\SignalRegistry;

class ConsumeCommand extends Command
{
    protected $signature = 'signal:consume
                            {--cursor= : Start from a specific cursor position}
                            {--fresh : Start from the beginning, ignoring stored cursor}
                            {--mode= : Override mode (jetstream or firehose)}';

    protected $description = 'Start consuming events from the AT Protocol';

    public function handle(SignalRegistry $registry): int
    {
        // Determine mode
        $mode = $this->option('mode') ?? config('signal.mode', 'jetstream');

        if (! in_array($mode, ['jetstream', 'firehose'])) {
            $this->error("Invalid mode: {$mode}. Must be 'jetstream' or 'firehose'.");

            return self::FAILURE;
        }

        $this->info("Signal: Initializing {$mode} consumer...");

        // Discover signals
        $registry->discover();

        $signalCount = $registry->all()->count();

        if ($signalCount === 0) {
            $this->warn('No signals registered. Create signals in app/Signals or register them in config/signal.php');

            return self::FAILURE;
        }

        // List registered signals with a prettier display
        $this->newLine();
        $this->components->info("Found {$signalCount} ".str('signal')->plural($signalCount));
        $this->newLine();

        $normalizeValue = fn ($value) => $value instanceof \BackedEnum ? $value->value : $value;

        foreach ($registry->all() as $signal) {
            $className = class_basename($signal);
            $eventTypes = collect($signal->eventTypes())
                ->map(fn ($type) => "<fg=cyan>{$normalizeValue($type)}</>")
                ->join(', ');
            $collections = $signal->collections()
                ? collect($signal->collections())->map(fn ($col) => "<fg=yellow>{$col}</>")->join(', ')
                : '<fg=gray>All collections</>';

            $operations = $signal->operations()
                ? collect($signal->operations())
                    ->map(fn ($op) => "<fg=magenta>{$normalizeValue($op)}</>")
                    ->join(', ')
                : '<fg=gray>All operations</>';

            $this->line("  <fg=green>•</> <options=bold>{$className}</> → {$eventTypes} | {$collections} | {$operations}");
        }

        $this->newLine();

        // Show mode-specific information
        if ($mode === 'jetstream') {
            $this->components->warn('Jetstream Mode: Server-side filtering (custom collections may not receive create/update)');
        } else {
            $this->components->info('Firehose Mode: Client-side filtering (all events received, including custom collections)');
        }

        $this->newLine();

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

        // Resolve and start the appropriate consumer
        $consumer = $mode === 'firehose'
            ? app(FirehoseConsumer::class)
            : app(JetstreamConsumer::class);

        $this->info("Starting {$mode} consumer... Press Ctrl+C to stop.");

        try {
            $consumer->start($cursor);
        } catch (\Exception $e) {
            $this->error('Error: '.$e->getMessage());

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
