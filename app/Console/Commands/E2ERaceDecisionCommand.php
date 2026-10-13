<?php

namespace App\Console\Commands;

use App\Actions\Auth\ApproveRegistration;
use App\Actions\Auth\RejectRegistration;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Symfony\Component\Console\Command\Command as SymfonyCommand;
use Throwable;

/**
 * Performs ONE registration decision, synchronised for a real-process race.
 *
 * E2E-27 proves that two genuinely simultaneous decisions (approve vs reject of
 * the same pending account) collapse into exactly one winner, with no
 * contradictory audit pair. A single PHPUnit process cannot interleave two
 * MySQL transactions, so the test starts two real `php artisan` processes and
 * barriers them on a file: each writes a READY file once booted and then waits
 * for the shared GATE file, so both reach the decision within microseconds of
 * each other.
 *
 * The command is intentionally NOT behind `GuardsE2EDatabase`: the PHPUnit
 * suite runs against the unmarked `testing` database, so requiring the `_e2e`
 * marker would make the race impossible. It refuses to run in anything other
 * than the testing/e2e environments declared in `config('e2e.environments')`,
 * and it can only act on the single user named on the command line.
 */
class E2ERaceDecisionCommand extends Command
{
    protected $signature = 'e2e:race-decision
        {action : approve|reject}
        {target : The target user email}
        {actor : The acting administrator email}
        {--ready-file= : File to write once the worker is booted and waiting}
        {--gate-file= : File whose appearance releases the worker}
        {--timeout=30 : Seconds to wait for the gate before giving up}';

    protected $description = 'Perform one registration decision, synchronised for a real-process race';

    /**
     * Execute the command.
     */
    public function handle(): int
    {
        if (! app()->environment((array) config('e2e.environments', ['testing', 'e2e']))) {
            $this->error('Refusing to run outside a testing environment.');

            return SymfonyCommand::FAILURE;
        }

        $action = (string) $this->argument('action');

        if (! in_array($action, ['approve', 'reject'], true)) {
            $this->error('The action must be approve or reject.');

            return SymfonyCommand::FAILURE;
        }

        $target = User::query()
            ->where('email', mb_strtolower(trim((string) $this->argument('target'))))
            ->first();

        $actor = User::query()
            ->where('email', mb_strtolower(trim((string) $this->argument('actor'))))
            ->first();

        if ($target === null || $actor === null) {
            $this->error('Unknown target or actor.');

            return SymfonyCommand::FAILURE;
        }

        try {
            $this->waitForGate();
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return SymfonyCommand::FAILURE;
        }

        try {
            $applied = $action === 'approve'
                ? app(ApproveRegistration::class)->execute($target, $actor)
                : app(RejectRegistration::class)->execute($target, $actor, 'e2e race decision');
        } catch (ValidationException $exception) {
            // The loser of the race sees the winner's committed state and is
            // refused by the state machine; that is the expected outcome.
            $this->result(['outcome' => 'conflict', 'message' => $exception->getMessage()]);

            return SymfonyCommand::SUCCESS;
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return SymfonyCommand::FAILURE;
        }

        $this->result(['outcome' => $applied ? 'applied' : 'noop']);

        return SymfonyCommand::SUCCESS;
    }

    /**
     * Announce readiness and block until the shared gate appears.
     *
     * @throws RuntimeException When the gate never appears.
     */
    private function waitForGate(): void
    {
        $readyFile = (string) $this->option('ready-file');
        $gateFile = (string) $this->option('gate-file');

        if ($readyFile !== '') {
            file_put_contents($readyFile, (string) getmypid());
        }

        if ($gateFile === '') {
            return;
        }

        $deadline = microtime(true) + (float) $this->option('timeout');

        while (! file_exists($gateFile)) {
            if (microtime(true) > $deadline) {
                throw new RuntimeException('Timed out waiting for the race gate.');
            }

            usleep(500);
        }
    }

    /**
     * Emit a machine-readable result line the test can parse.
     *
     * @param  array<string, string>  $payload  The result payload.
     */
    private function result(array $payload): void
    {
        $this->line('E2E_RESULT '.json_encode($payload, JSON_THROW_ON_ERROR));
    }
}
