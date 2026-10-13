<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\GuardsE2EDatabase;
use Illuminate\Console\Command;

/**
 * Runs the isolated application server the browser suite talks to.
 *
 * Playwright launches this as its `webServer`, with APP_ENV and DB_DATABASE
 * already set to the E2E pair. The command makes sure the database exists and
 * is migrated, then delegates to the framework's `serve` command on the
 * loopback interface. The developer's own app instance is never touched.
 */
class E2EServeCommand extends Command
{
    use GuardsE2EDatabase;

    protected $signature = 'e2e:serve {--host= : The interface to bind} {--port= : The port to bind}';

    protected $description = 'Start the isolated E2E application server';

    /**
     * Execute the command.
     */
    public function handle(): int
    {
        if (! $this->guardE2EDatabase()) {
            return self::FAILURE;
        }

        $this->ensureE2EDatabaseExists();
        $this->call('migrate', ['--force' => true]);

        $host = (string) ($this->option('host') ?: config('e2e.host', '127.0.0.1'));
        $port = (string) ($this->option('port') ?: config('e2e.port', 8010));

        $this->info(sprintf('E2E server listening on http://%s:%s', $host, $port));

        return $this->call('serve', [
            '--host' => $host,
            '--port' => $port,
            // The framework's `serve` command only forwards a small whitelist of
            // environment variables (APP_ENV, PATH, ...) to the `php -S` child,
            // which would drop DB_DATABASE and silently serve the developer's
            // database. `--no-reload` makes it forward the whole environment
            // instead, which is exactly what an isolated E2E server needs.
            '--no-reload' => true,
        ]);
    }
}
