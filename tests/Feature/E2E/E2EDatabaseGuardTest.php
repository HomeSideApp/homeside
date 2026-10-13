<?php

namespace Tests\Feature\E2E;

use Illuminate\Console\OutputStyle;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Tests\TestCase;

/**
 * The E2E commands can drop every table, so the guard that decides whether they
 * may run is the single most important thing to prove.
 *
 * Two independent conditions are required and both are covered here: a testing
 * environment AND a database whose name carries the E2E marker. The PHPUnit
 * suite runs against the unmarked `testing` database, which is exactly the case
 * that must be refused.
 */
class E2EDatabaseGuardTest extends TestCase
{
    public function test_lines_the_guards_for_the_destructive_commands(): void
    {
        // Sanity check: the PHPUnit environment itself is a testing one but the
        // database name is unmarked, so a probe built from it must refuse.
        [$allowed, $message] = $this->probe();

        $this->assertFalse($allowed);
        $this->assertStringContainsString('not an E2E database', $message);
    }

    public function test_a_non_testing_environment_is_refused_even_with_a_marked_database(): void
    {
        config(['database.connections.mysql.database' => 'testing_e2e']);

        foreach (['local', 'staging', 'production'] as $environment) {
            app()['env'] = $environment;

            [$allowed, $message] = $this->probe();

            $this->assertFalse($allowed, "The guard allowed the [{$environment}] environment.");
            $this->assertStringContainsString("APP_ENV is \"{$environment}\"", $message);
        }
    }

    public function test_a_testing_environment_with_a_marked_database_is_allowed(): void
    {
        app()['env'] = 'e2e';
        config(['database.connections.mysql.database' => 'testing_e2e']);

        [$allowed, $message] = $this->probe();

        $this->assertTrue($allowed, $message);
        $this->assertSame('', trim($message));
    }

    public function test_a_database_without_the_marker_is_refused_even_in_a_testing_environment(): void
    {
        app()['env'] = 'testing';

        foreach (['laravel', 'testing', 'testing_e2e_backup'] as $database) {
            config(['database.connections.mysql.database' => $database]);

            [$allowed, $message] = $this->probe();

            $this->assertFalse($allowed, "The guard allowed the [{$database}] database.");
            $this->assertStringContainsString('not an E2E database', $message);
        }
    }

    public function test_the_real_destructive_commands_refuse_an_unmarked_database(): void
    {
        $this->artisan('e2e:seed')
            ->expectsOutputToContain('not an E2E database')
            ->assertExitCode(1);

        $this->artisan('e2e:reset')
            ->expectsOutputToContain('not an E2E database')
            ->assertExitCode(1);

        $this->artisan('e2e:policy', ['channel' => 'web', 'policy' => 'open'])
            ->expectsOutputToContain('not an E2E database')
            ->assertExitCode(1);

        $this->artisan('e2e:user-state', ['email' => 'nobody@homeside.test'])
            ->expectsOutputToContain('not an E2E database')
            ->assertExitCode(1);
    }

    public function test_the_real_destructive_commands_refuse_a_non_testing_environment(): void
    {
        app()['env'] = 'local';
        config(['database.connections.mysql.database' => 'testing_e2e']);

        $this->artisan('e2e:reset')
            ->expectsOutputToContain('APP_ENV is "local"')
            ->assertExitCode(1);
    }

    /**
     * Build a probe command with a buffered output and run the guard.
     *
     * @return array{0: bool, 1: string} The decision and everything it printed.
     */
    private function probe(): array
    {
        $command = new GuardProbeCommand;
        $command->setLaravel($this->app);

        $buffer = new BufferedOutput(OutputInterface::VERBOSITY_NORMAL, true);
        $command->setOutput(new OutputStyle(new ArrayInput([]), $buffer));

        $allowed = $command->probe();

        return [$allowed, $buffer->fetch()];
    }
}
