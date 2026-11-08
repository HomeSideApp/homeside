<?php

namespace Tests\Feature\E2E;

use App\Models\RegistrationAudit;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use RuntimeException;
use Symfony\Component\Process\Process;
use Tests\TestCase;

/**
 * E2E-27 — Two simultaneous registration decisions collapse into exactly one.
 *
 * Approving and rejecting the same pending account are opposite decisions. The
 * domain actions lock the user row FOR UPDATE inside one transaction, re-read
 * the committed state and refuse the opposite transition, so at most one can
 * apply. This test proves that with TWO REAL PROCESSES (not two sequential
 * calls in one PHP process, and not a browser): each boots Laravel, writes a
 * READY file, waits for a shared GATE file, then issues its decision within
 * microseconds of the other.
 *
 * It asserts the observable contract: exactly one decision applies, the final
 * status agrees with it, and the audit trail contains no contradictory pair
 * (never both `approved` and `rejected` for the account). The database is
 * truncated (not wrapped in a transaction, and never rolled back) because a
 * sibling process cannot see uncommitted rows.
 */
class RegistrationDecisionConcurrencyTest extends TestCase
{
    use DatabaseTruncation;

    private string $barrierDir = '';

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        $this->barrierDir = sys_get_temp_dir().'/homeside-e2e-race-'.bin2hex(random_bytes(6));

        if (! mkdir($this->barrierDir, 0700, true) && ! is_dir($this->barrierDir)) {
            throw new RuntimeException('Could not create the race barrier directory.');
        }
    }

    protected function tearDown(): void
    {
        foreach (glob($this->barrierDir.'/*') ?: [] as $file) {
            @unlink($file);
        }

        if ($this->barrierDir !== '' && is_dir($this->barrierDir)) {
            @rmdir($this->barrierDir);
        }

        // The workers committed real rows, so the shared test database must be
        // cleared before the next test (its own transaction would not remove
        // them). Truncation is the trait's own primitives, and it never rolls
        // back via a migration's `down()`.
        $this->truncateDatabaseTables();

        parent::tearDown();
    }

    public function test_exactly_one_of_two_simultaneous_decisions_wins(): void
    {
        $actor = User::factory()->admin()->create();
        $target = User::factory()->create([
            'email' => 'race-target@example.com',
            'approval_status' => 'pending',
        ]);
        $target->assignRole('user');

        $approve = $this->worker('approve', $target->email, $actor->email);
        $reject = $this->worker('reject', $target->email, $actor->email);

        $approve->start();
        $reject->start();

        $this->waitUntilReady($approve, 'approve');
        $this->waitUntilReady($reject, 'reject');

        // Release both workers at the same instant.
        file_put_contents($this->barrierDir.'/gate', 'go');

        $approve->wait();
        $reject->wait();

        $outcomes = [$this->outcome($approve), $this->outcome($reject)];
        sort($outcomes);

        $this->assertSame(
            ['applied', 'conflict'],
            $outcomes,
            $this->diagnostics($approve, $reject),
        );

        $target->refresh();
        $this->assertContains($target->approval_status, ['approved', 'rejected']);

        $approvedAudit = RegistrationAudit::query()
            ->where('user_id', $target->id)
            ->where('action', 'approved')
            ->count();

        $rejectedAudit = RegistrationAudit::query()
            ->where('user_id', $target->id)
            ->where('action', 'rejected')
            ->count();

        $this->assertSame(1, $approvedAudit + $rejectedAudit, 'Exactly one decision must be audited.');
        $this->assertSame(
            $target->approval_status === 'approved' ? 1 : 0,
            $approvedAudit,
            'The audit trail must not contradict the final status.',
        );
        $this->assertSame(
            $target->approval_status === 'rejected' ? 1 : 0,
            $rejectedAudit,
            'The audit trail must not contain a contradictory approved/rejected pair.',
        );
    }

    /**
     * Build one real worker process for a decision.
     */
    private function worker(string $action, string $target, string $actor): Process
    {
        return new Process([
            PHP_BINARY,
            base_path('artisan'),
            'e2e:race-decision',
            $action,
            $target,
            $actor,
            '--ready-file='.$this->barrierDir.'/'.$action.'.ready',
            '--gate-file='.$this->barrierDir.'/gate',
            '--timeout=30',
        ], base_path(), $this->workerEnvironment());
    }

    /**
     * The environment a worker needs to reach the same committed database.
     *
     * PHPUnit's `<env>` values are not guaranteed to be exported to child
     * processes, so the connection is passed explicitly from the running
     * configuration instead of assumed.
     *
     * @return array<string, string> The child environment.
     */
    private function workerEnvironment(): array
    {
        $connection = (string) config('database.default');
        $database = (array) config("database.connections.$connection");

        return [
            'APP_ENV' => 'testing',
            'APP_KEY' => (string) config('app.key'),
            'APP_DEBUG' => 'true',
            'DB_CONNECTION' => $connection,
            'DB_HOST' => (string) ($database['host'] ?? '127.0.0.1'),
            'DB_PORT' => (string) ($database['port'] ?? '3306'),
            'DB_DATABASE' => (string) ($database['database'] ?? 'testing'),
            'DB_USERNAME' => (string) ($database['username'] ?? ''),
            'DB_PASSWORD' => (string) ($database['password'] ?? ''),
            'CACHE_STORE' => 'array',
            'SESSION_DRIVER' => 'array',
            'QUEUE_CONNECTION' => 'sync',
            'MAIL_MAILER' => 'array',
            'BROADCAST_CONNECTION' => 'null',
            'BCRYPT_ROUNDS' => '4',
            'PULSE_ENABLED' => 'false',
            'TELESCOPE_ENABLED' => 'false',
            'NIGHTWATCH_ENABLED' => 'false',
            'SAAS_ENABLED' => 'false',
        ];
    }

    /**
     * Wait until a worker announced it booted and is waiting on the gate.
     */
    private function waitUntilReady(Process $process, string $action): void
    {
        $file = $this->barrierDir.'/'.$action.'.ready';
        $deadline = microtime(true) + 30;

        while (! file_exists($file)) {
            if (! $process->isRunning()) {
                $this->fail("The [{$action}] worker exited before it was ready.\n".$process->getErrorOutput());
            }

            if (microtime(true) > $deadline) {
                $this->fail("The [{$action}] worker never became ready.\n".$process->getErrorOutput());
            }

            usleep(10_000);
        }
    }

    /**
     * Parse the decision outcome a worker printed.
     */
    private function outcome(Process $process): string
    {
        foreach (array_reverse(preg_split('/\R/', (string) $process->getOutput()) ?: []) as $line) {
            if (! str_starts_with(trim($line), 'E2E_RESULT ')) {
                continue;
            }

            $payload = json_decode(substr(trim($line), strlen('E2E_RESULT ')), true);

            if (is_array($payload) && isset($payload['outcome'])) {
                return (string) $payload['outcome'];
            }
        }

        return 'unknown';
    }

    /**
     * Human diagnostics for a failed race.
     */
    private function diagnostics(Process $approve, Process $reject): string
    {
        return sprintf(
            "approve: exit=%d out=%s err=%s\nreject: exit=%d out=%s err=%s",
            $approve->getExitCode() ?? -1,
            trim($approve->getOutput()),
            trim($approve->getErrorOutput()),
            $reject->getExitCode() ?? -1,
            trim($reject->getOutput()),
            trim($reject->getErrorOutput()),
        );
    }
}
