<?php

namespace SocialDept\Signal;

use Illuminate\Support\ServiceProvider;
use SocialDept\Signal\Commands\ConsumeCommand;
use SocialDept\Signal\Commands\InstallCommand;
use SocialDept\Signal\Commands\ListSignalsCommand;
use SocialDept\Signal\Commands\MakeSignalCommand;
use SocialDept\Signal\Commands\TestSignalCommand;
use SocialDept\Signal\Contracts\CursorStore;
use SocialDept\Signal\Services\EventDispatcher;
use SocialDept\Signal\Services\JetstreamConsumer;
use SocialDept\Signal\Services\SignalRegistry;
use SocialDept\Signal\Storage\DatabaseCursorStore;
use SocialDept\Signal\Storage\FileCursorStore;
use SocialDept\Signal\Storage\RedisCursorStore;

class SignalServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/signal.php', 'signal');

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
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            // Publish config
            $this->publishes([
                __DIR__ . '/../config/signal.php' => config_path('signal.php'),
            ], 'signal-config');

            // Publish migrations
            $this->publishes([
                __DIR__ . '/../database/migrations' => database_path('migrations'),
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
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
    }
}
