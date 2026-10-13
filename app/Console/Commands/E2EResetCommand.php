<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\GuardsE2EDatabase;
use Database\Seeders\E2EDatabaseSeeder;
use Illuminate\Console\Command;

/**
 * Rebuilds the E2E database from scratch.
 *
 * `migrate:fresh` drops EVERY table, so the guard is not a convenience: without
 * it a stray run against the development database would destroy real data.
 */
class E2EResetCommand extends Command
{
    use GuardsE2EDatabase;

    protected $signature = 'e2e:reset';

    protected $description = 'Drop and rebuild the isolated E2E database, then seed the fixtures';

    /**
     * Execute the command.
     */
    public function handle(): int
    {
        if (! $this->guardE2EDatabase()) {
            return self::FAILURE;
        }

        return $this->runGuarded(function (): void {
            $this->ensureE2EDatabaseExists();
            $this->call('migrate:fresh', ['--force' => true]);
            $this->call('db:seed', ['--class' => E2EDatabaseSeeder::class, '--force' => true]);

            $this->info('E2E database reset.');
        });
    }
}
