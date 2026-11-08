<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\GuardsE2EDatabase;
use Database\Seeders\E2EDatabaseSeeder;
use Illuminate\Console\Command;

/**
 * Seeds the fixtures the browser suite depends on.
 *
 * It migrates (without wiping) and then runs the idempotent E2E seeder, so it
 * can be re-run safely. Use `e2e:reset` when the database must be rebuilt from
 * scratch between specs.
 */
class E2ESeedCommand extends Command
{
    use GuardsE2EDatabase;

    protected $signature = 'e2e:seed';

    protected $description = 'Seed the isolated E2E database with the browser-test fixtures';

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
            $this->call('migrate', ['--force' => true]);
            // The login, OTP and registration throttles are cache-backed; flush
            // them so one scenario's attempts never rate-limit the next one.
            $this->call('cache:clear');
            $this->call('db:seed', ['--class' => E2EDatabaseSeeder::class, '--force' => true]);

            $this->info('E2E database seeded.');
        });
    }
}
