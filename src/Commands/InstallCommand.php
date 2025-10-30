<?php

namespace SocialDept\Signal\Commands;

use Illuminate\Console\Command;

class InstallCommand extends Command
{
    protected $signature = 'signal:install';

    protected $description = 'Install the Signal package (publish config, migrations, and run migrations)';

    public function handle(): int
    {
        $this->info('Installing Signal package...');
        $this->newLine();

        // Publish config
        $this->comment('Publishing configuration...');
        $this->callSilently('vendor:publish', [
            '--tag' => 'signal-config',
            '--force' => $this->option('force', false),
        ]);
        $this->info('✓ Configuration published');

        // Publish migrations
        $this->comment('Publishing migrations...');
        $this->callSilently('vendor:publish', [
            '--tag' => 'signal-migrations',
            '--force' => $this->option('force', false),
        ]);
        $this->info('✓ Migrations published');

        // Run migrations
        $this->newLine();
        $this->comment('Running migrations...');

        if ($this->confirm('Do you want to run the migrations now?', true)) {
            $this->call('migrate');
            $this->info('✓ Migrations completed');
        } else {
            $this->warn('⚠ Skipped migrations. Run "php artisan migrate" manually when ready.');
        }

        $this->newLine();
        $this->info('Signal package installed successfully!');
        $this->newLine();

        // Show next steps
        $this->line('Next steps:');
        $this->line('1. Review the config file: config/signal.php');
        $this->line('2. Create your first signal: php artisan make:signal NewPostSignal');
        $this->line('3. Start consuming events: php artisan signal:consume');

        return self::SUCCESS;
    }
}
