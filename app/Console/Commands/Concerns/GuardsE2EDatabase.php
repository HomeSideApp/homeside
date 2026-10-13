<?php

namespace App\Console\Commands\Concerns;

use PDO;
use PDOException;
use Throwable;

/**
 * The shared safety gate for every destructive E2E command.
 *
 * Two independent conditions must BOTH hold before an E2E command is allowed
 * to touch a database:
 *
 *  1. the application reports a testing environment (`testing` or `e2e`);
 *  2. the resolved database name carries the dedicated E2E marker (`_e2e`).
 *
 * Requiring both means a single mistake is never enough to wipe the wrong
 * database: exporting `APP_ENV=testing` still points at the unmarked
 * development database, and naming a database `something_e2e` while running
 * `APP_ENV=production` still fails the environment check. The PHPUnit suite
 * runs against the unmarked `testing` database, so it can never trigger a wipe.
 */
trait GuardsE2EDatabase
{
    /**
     * Refuse to continue unless the environment and the database are E2E.
     *
     * @return bool True when the command may proceed.
     */
    protected function guardE2EDatabase(): bool
    {
        $environments = (array) config('e2e.environments', ['testing', 'e2e']);
        $environment = app()->environment();

        if (! in_array($environment, $environments, true)) {
            $this->error(sprintf(
                'Refusing to run: APP_ENV is "%s"; this command only runs in: %s.',
                $environment,
                implode(', ', $environments),
            ));

            return false;
        }

        $database = $this->resolvedDatabaseName();
        $marker = (string) config('e2e.database_marker');

        if ($marker === '' || ! str_ends_with($database, $marker)) {
            $this->error(sprintf(
                'Refusing to run: the database "%s" is not an E2E database (it must end with "%s").',
                $database,
                $marker,
            ));

            return false;
        }

        return true;
    }

    /**
     * Create the E2E database when it does not exist yet.
     *
     * The MySQL image grants the application user `ALL PRIVILEGES ON testing%`,
     * so a database named `testing_e2e` can be created by the application user
     * itself without root credentials. Only the MySQL driver is supported:
     * every other driver either creates the database out of band (SQLite) or is
     * out of scope for the E2E stack.
     */
    protected function ensureE2EDatabaseExists(): void
    {
        $connection = (string) config('database.default');
        $config = (array) config("database.connections.$connection");

        if (($config['driver'] ?? null) !== 'mysql') {
            return;
        }

        $database = $this->resolvedDatabaseName();

        try {
            $pdo = $this->serverConnection($config);
            $pdo->exec(sprintf('CREATE DATABASE IF NOT EXISTS `%s`', str_replace('`', '', $database)));
        } catch (PDOException $exception) {
            $this->error(sprintf(
                'Could not create the E2E database "%s": %s',
                $database,
                $exception->getMessage(),
            ));
            throw $exception;
        } finally {
            unset($pdo);
        }
    }

    /**
     * Resolve the database name the default connection points at.
     *
     * @return string The database name.
     */
    protected function resolvedDatabaseName(): string
    {
        $connection = (string) config('database.default');

        return (string) config("database.connections.$connection.database");
    }

    /**
     * Build a PDO connection to the server WITHOUT selecting a database.
     *
     * The default Eloquent connection cannot be used here: it selects the E2E
     * database, which does not exist yet the first time the command runs.
     *
     * @param  array<string, mixed>  $config  The connection configuration.
     * @return PDO The server-level connection.
     */
    private function serverConnection(array $config): PDO
    {
        $dsn = sprintf(
            'mysql:host=%s;port=%s;charset=utf8mb4',
            $config['host'] ?? '127.0.0.1',
            $config['port'] ?? '3306',
        );

        return new PDO($dsn, (string) ($config['username'] ?? ''), (string) ($config['password'] ?? ''), [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_TIMEOUT => 5,
        ]);
    }

    /**
     * Run a callback, reporting a clean failure instead of a stack trace.
     *
     * @param  callable():void  $callback  The work to run.
     * @return int The command exit code.
     */
    protected function runGuarded(callable $callback): int
    {
        try {
            $callback();
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
