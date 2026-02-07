<?php

namespace SocialDept\AtpSignals;

use Illuminate\Support\ServiceProvider;
use SocialDept\AtpSignals\Commands\ConsumeCommand;
use SocialDept\AtpSignals\Commands\InstallCommand;
use SocialDept\AtpSignals\Commands\ListSignalsCommand;
use SocialDept\AtpSignals\Commands\MakeSignalCommand;
use SocialDept\AtpSignals\Commands\TestSignalCommand;
use SocialDept\AtpSignals\Contracts\CursorStore;
use SocialDept\AtpSignals\Services\EventDispatcher;
use SocialDept\AtpSignals\Services\FirehoseConsumer;
use SocialDept\AtpSignals\Services\JetstreamConsumer;
use SocialDept\AtpSignals\Services\SignalManager;
use SocialDept\AtpSignals\Services\SignalRegistry;
use SocialDept\AtpSignals\Storage\DatabaseCursorStore;
use SocialDept\AtpSignals\Storage\FileCursorStore;
use SocialDept\AtpSignals\Storage\RedisCursorStore;

class SignalServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/atp-signals.php', 'atp-signals');

        // Register cursor store
        $this->app->singleton(CursorStore::class, function ($app) {
            return match (config('atp-signals.cursor_storage')) {
                'redis' => new RedisCursorStore(),
                'file' => new FileCursorStore(),
                default => new DatabaseCursorStore(),
            };
        });

        // Register signal registry
        $this->app->singleton(SignalRegistry::class, function ($app) {
            $registry = new SignalRegistry();

            // Register configured signals
            foreach (config('atp-signals.signals', []) as $signal) {
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
                __DIR__.'/../config/atp-signals.php' => config_path('atp-signals.php'),
            ], 'atp-signals-config');

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
