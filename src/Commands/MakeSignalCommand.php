<?php

namespace SocialDept\Signals\Commands;

use Illuminate\Console\GeneratorCommand;
use Symfony\Component\Console\Input\InputOption;

class MakeSignalCommand extends GeneratorCommand
{
    protected $name = 'make:signal';

    protected $description = 'Create a new Signal class';

    protected $type = 'Signal';

    protected function getStub(): string
    {
        return __DIR__ . '/../../stubs/signal.stub';
    }

    protected function getDefaultNamespace($rootNamespace): string
    {
        return $rootNamespace . '\\Signals';
    }

    protected function buildClass($name): string
    {
        $stub = parent::buildClass($name);

        $eventType = $this->option('type') ?? 'commit';
        $collection = $this->option('collection') ?? 'app.bsky.feed.post';

        $stub = str_replace('{{ eventType }}', $eventType, $stub);
        $stub = str_replace('{{ collection }}', $collection, $stub);

        return $stub;
    }

    protected function getOptions(): array
    {
        return [
            ['type', 't', InputOption::VALUE_OPTIONAL, 'The event type (commit, identity, account)'],
            ['collection', 'c', InputOption::VALUE_OPTIONAL, 'The collection to watch'],
        ];
    }
}
