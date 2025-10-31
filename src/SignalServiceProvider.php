<?php

namespace SocialDept\Signals;

use Illuminate\Support\ServiceProvider;
use SocialDept\Signals\Commands\ConsumeCommand;
use SocialDept\Signals\Commands\InstallCommand;
use SocialDept\Signals\Commands\ListSignalsCommand;
use SocialDept\Signals\Commands\MakeSignalCommand;
use SocialDept\Signals\Commands\TestSignalCommand;
use SocialDept\Signals\Contracts\CursorStore;
use SocialDept\Signals\Services\EventDispatcher;
use SocialDept\Signals\Services\FirehoseConsumer;
use SocialDept\Signals\Services\JetstreamConsumer;
use SocialDept\Signals\Services\SignalManager;
use SocialDept\Signals\Services\SignalRegistry;
use SocialDept\Signals\Storage\DatabaseCursorStore;
use SocialDept\Signals\Storage\FileCursorStore;
use SocialDept\Signals\Storage\RedisCursorStore;

class SignalServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/signal.php', 'signal');

        // Register cursor store
        $this->app->singleton(CursorStore::class, function ($app) {
            return match (config('signal.cursor_storage')) {
                'redis' => new RedisCursorStore(),
                'file' => new FileCursorStore(),
                default => new DatabaseCursorStore(),
            };
        });

        // Register signal registry
        $this->app->singleton(SignalRegistry::class, function ($app) {
            $registry = new SignalRegistry();

            // Register configured signals
            foreach (config('signal.signals', []) as $signal) {
                $registry->register($signal);
            }

            return $registry;
        });

        // Register event dispatcher
        $this->app->singleton(EventDispatcher::class, function ($app) {
            return new EventDispatcher($app->make(SignalRegistry::class));
        });

        // Register Jetstream consumer
        $this->app->singleton(JetstreamConsumer::class, function ($app) {
            return new JetstreamConsumer(
                $app->make(CursorStore::class),
                $app->make(SignalRegistry::class),
                $app->make(EventDispatcher::class),
            );
        });

        // Register Firehose consumer
        $this->app->singleton(FirehoseConsumer::class, function ($app) {
            return new FirehoseConsumer(
                $app->make(CursorStore::class),
                $app->make(SignalRegistry::class),
                $app->make(EventDispatcher::class),
            );
        });

        // Register Signal manager
        $this->app->singleton(SignalManager::class, function ($app) {
            return new SignalManager(
                $app->make(FirehoseConsumer::class),
                $app->make(JetstreamConsumer::class),
            );
        });
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            // Publish config
            $this->publishes([
                __DIR__.'/../config/signal.php' => config_path('signal.php'),
            ], 'signal-config');

            // Publish migrations
            $this->publishes([
                __DIR__.'/../database/migrations' => database_path('migrations'),
            ], 'signal-migrations');

            // Register commands
            $this->commands([
                InstallCommand::class,
                ConsumeCommand::class,
                ListSignalsCommand::class,
                MakeSignalCommand::class,
                TestSignalCommand::class,
            ]);
        }

        // Load migrations
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
    }
}
