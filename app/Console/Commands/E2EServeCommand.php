<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\GuardsE2EDatabase;
use App\Support\Auth\E2EGoogleSimulator;
use Illuminate\Console\Command;
use RuntimeException;

/**
 * Runs the isolated application server the browser suite talks to.
 *
 * Playwright launches this as its `webServer`, with APP_ENV and DB_DATABASE
 * already set to the E2E pair. The command makes sure the database exists and
 * is migrated, then delegates to the framework's `serve` command on the
 * loopback interface. The developer's own app instance is never touched.
 *
 * When the simulated Google provider is enabled it ALSO starts a second,
 * independent `php -S` listener for the simulator. The application calls the
 * token and userinfo endpoints server-to-server while it is handling the
 * callback, so those endpoints must not live on the same single-threaded server
 * that handles the callback: doing so would deadlock. The simulator is its own
 * process on its own port, and it is stopped when this command exits.
 */
class E2EServeCommand extends Command
{
    use GuardsE2EDatabase;

    protected $signature = 'e2e:serve {--host= : The interface to bind} {--port= : The port to bind}';

    protected $description = 'Start the isolated E2E application server';

    /** @var resource|null The simulator child process, when one was started. */
    private mixed $simulatorProcess = null;

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

        $this->startGoogleSimulator();

        register_shutdown_function(fn () => $this->stopGoogleSimulator());

        if (function_exists('pcntl_async_signals')) {
            pcntl_async_signals(true);

            foreach ([SIGINT, SIGTERM] as $signal) {
                pcntl_signal($signal, function (): void {
                    $this->stopGoogleSimulator();

                    exit(0);
                });
            }
        }

        $this->info(sprintf('E2E server listening on http://%s:%s', $host, $port));

        try {
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
        } finally {
            $this->stopGoogleSimulator();
        }
    }

    /**
     * Start the simulated Google provider on its own listener.
     *
     * Fail-closed by design: the simulator only starts when `E2EGoogleSimulator`
     * says the E2E environment, the loopback URL and the credentials all agree.
     * Outside E2E nothing starts and the application keeps using real Google.
     */
    private function startGoogleSimulator(): void
    {
        $simulator = app(E2EGoogleSimulator::class);

        if (! $simulator->enabled()) {
            $this->line('Google simulator disabled; the E2E server will use the real Google provider.');

            return;
        }

        $host = (string) config('e2e.google_simulator.host', '127.0.0.1');
        $port = (int) config('e2e.google_simulator.port', 8011);
        $router = (string) config('e2e.google_simulator.router');
        $baseUrl = $simulator->baseUrl();
        $pidFile = (string) config('e2e.google_simulator.pid_file');
        $logFile = (string) config('e2e.google_simulator.log_file');

        if (! is_file($router)) {
            throw new RuntimeException("The Google simulator router [{$router}] does not exist.");
        }

        $this->stopStaleSimulator($pidFile, $host, $port);

        $logDirectory = dirname($logFile);

        if (! is_dir($logDirectory)) {
            mkdir($logDirectory, 0775, true);
        }

        $descriptors = [
            0 => ['file', '/dev/null', 'r'],
            1 => ['file', $logFile, 'a'],
            2 => ['file', $logFile, 'a'],
        ];

        $process = proc_open([PHP_BINARY, '-S', "{$host}:{$port}", $router], $descriptors, $pipes);

        if (! is_resource($process)) {
            throw new RuntimeException('Could not start the Google simulator process.');
        }

        $this->simulatorProcess = $process;

        $status = proc_get_status($process);
        file_put_contents($pidFile, (string) ($status['pid'] ?? ''));

        if (! $this->waitForSimulator($baseUrl)) {
            $this->error(sprintf('The Google simulator did not answer on %s/__health.', $baseUrl));

            return;
        }

        $this->info(sprintf('Google simulator listening on %s', $baseUrl));
    }

    /**
     * Stop a simulator left behind by a previous run.
     *
     * The state file and the port are process-global, so an orphaned listener
     * would answer with stale code and stale state. Only the PID recorded in our
     * own pid file is touched, and only after confirming it is still alive.
     *
     * @param  string  $pidFile  The recorded PID file.
     * @param  string  $host  The simulator host.
     * @param  int  $port  The simulator port.
     */
    private function stopStaleSimulator(string $pidFile, string $host, int $port): void
    {
        if (is_file($pidFile)) {
            $pid = (int) trim((string) file_get_contents($pidFile));

            if ($pid > 0 && $pid !== getmypid() && function_exists('posix_kill') && posix_kill($pid, 0)) {
                posix_kill($pid, SIGTERM);

                for ($attempt = 0; $attempt < 30; $attempt++) {
                    if (! @fsockopen($host, $port, $errorCode, $errorMessage, 0.1)) {
                        break;
                    }

                    usleep(100_000);
                }
            }

            @unlink($pidFile);
        }
    }

    /**
     * Wait until the simulator answers its health probe.
     *
     * @param  string  $baseUrl  The simulator base URL.
     * @return bool True when the simulator answered.
     */
    private function waitForSimulator(string $baseUrl): bool
    {
        $context = stream_context_create([
            'http' => ['timeout' => 0.5, 'ignore_errors' => true, 'method' => 'GET'],
        ]);

        for ($attempt = 0; $attempt < 50; $attempt++) {
            $response = @file_get_contents($baseUrl.'/__health', false, $context);

            if (is_string($response) && str_contains($response, '"ok"')) {
                return true;
            }

            usleep(100_000);
        }

        return false;
    }

    /**
     * Stop the simulator child and remove its PID file.
     */
    private function stopGoogleSimulator(): void
    {
        if (is_resource($this->simulatorProcess)) {
            proc_terminate($this->simulatorProcess, SIGTERM);

            for ($attempt = 0; $attempt < 20; $attempt++) {
                $status = proc_get_status($this->simulatorProcess);

                if (($status['running'] ?? false) === false) {
                    break;
                }

                usleep(50_000);
            }

            $status = proc_get_status($this->simulatorProcess);

            if (($status['running'] ?? false) === true) {
                proc_terminate($this->simulatorProcess, SIGKILL);
            }

            proc_close($this->simulatorProcess);
            $this->simulatorProcess = null;
        }

        $pidFile = (string) config('e2e.google_simulator.pid_file');

        if ($pidFile !== '' && is_file($pidFile)) {
            @unlink($pidFile);
        }
    }
}
