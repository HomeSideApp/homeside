<?php

namespace App\Console\Commands;

use App\Actions\Auth\CreateAuthenticationBootstrap;
use App\Console\Commands\Concerns\GuardsE2EDatabase;
use App\Enums\AuthenticationBootstrapPurpose;
use App\Models\AuthenticationBootstrap;
use App\Models\User;
use Illuminate\Console\Command;

/**
 * Prepares and inspects single-use authentication bootstraps for the browser
 * suite.
 *
 * The security matrix in E2E-09 needs grants that are expired, already
 * consumed, purpose-bound or issued long before a rejection. Those states are
 * not reachable through the browser alone, so this command prepares them
 * against the real repository and the real persistence layer. It is a state
 * PREPARATION and INSPECTION helper: a scenario that is meant to verify a
 * browser step still drives that step through the browser.
 *
 * The plaintext token is printed exactly once (the repository returns it once
 * and stores only its keyed digest) and never logged by the application.
 */
class E2EBootstrapCommand extends Command
{
    use GuardsE2EDatabase;

    protected $signature = 'e2e:bootstrap
        {action : issue|expire|consume|list}
        {email : The fixture email the grant belongs to}
        {--purpose=two_factor : two_factor|account_recovery}
        {--minutes=30 : Lifetime of an issued grant}';

    protected $description = 'Prepare and inspect single-use authentication bootstraps on the E2E database';

    /**
     * Execute the command.
     */
    public function handle(CreateAuthenticationBootstrap $create): int
    {
        if (! $this->guardE2EDatabase()) {
            return self::FAILURE;
        }

        $email = mb_strtolower(trim((string) $this->argument('email')));
        $purpose = $this->resolvePurpose();

        if ($purpose === null) {
            $this->error('Unknown purpose. Use two_factor or account_recovery.');

            return self::FAILURE;
        }

        $user = User::query()->where('email', $email)->first();

        if ($user === null) {
            $this->error(sprintf('No E2E fixture is seeded for %s.', $email));

            return self::FAILURE;
        }

        return $this->runGuarded(function () use ($create, $user, $purpose): void {
            $action = (string) $this->argument('action');

            match ($action) {
                'issue' => $this->issue($create, $user, $purpose),
                'expire' => $this->expire($user, $purpose),
                'consume' => $this->consume($user, $purpose),
                'list' => $this->listGrants($user),
                default => throw new \InvalidArgumentException(
                    'Unknown action. Use issue, expire, consume or list.'
                ),
            };
        });
    }

    /**
     * Issue a fresh grant through the production action and print its token once.
     */
    private function issue(CreateAuthenticationBootstrap $create, User $user, AuthenticationBootstrapPurpose $purpose): void
    {
        $minutes = max(1, (int) $this->option('minutes'));
        $token = $create->execute($user, $purpose);

        // CreateAuthenticationBootstrap uses the production TTL; when the
        // scenario needs a different lifetime the row is adjusted here so the
        // real issuance path still ran first.
        if ($minutes !== CreateAuthenticationBootstrap::TTL_MINUTES) {
            AuthenticationBootstrap::query()
                ->where('user_id', $user->getKey())
                ->where('purpose', $purpose->value)
                ->whereNull('consumed_at')
                ->update(['expires_at' => now()->addMinutes($minutes)]);
        }

        $this->line(json_encode([
            'token' => $token,
            'purpose' => $purpose->value,
            'url' => $this->url($token, $purpose),
            'path' => parse_url($this->url($token, $purpose), PHP_URL_PATH),
            'expires_at' => now()->addMinutes($minutes)->toIso8601String(),
        ], JSON_THROW_ON_ERROR));
    }

    /**
     * Mark every outstanding grant of the purpose as already expired.
     */
    private function expire(User $user, AuthenticationBootstrapPurpose $purpose): void
    {
        $affected = AuthenticationBootstrap::query()
            ->where('user_id', $user->getKey())
            ->where('purpose', $purpose->value)
            ->whereNull('consumed_at')
            ->update(['expires_at' => now()->subMinute()]);

        $this->line(json_encode(['expired' => $affected], JSON_THROW_ON_ERROR));
    }

    /**
     * Mark the newest outstanding grant of the purpose as consumed.
     */
    private function consume(User $user, AuthenticationBootstrapPurpose $purpose): void
    {
        // The plaintext token is not available here, so the grant is marked
        // spent directly. This only PREPARES the "already-consumed" state; the
        // single-use atomicity itself is exercised through the browser.
        $affected = AuthenticationBootstrap::query()
            ->where('user_id', $user->getKey())
            ->where('purpose', $purpose->value)
            ->whereNull('consumed_at')
            ->update(['consumed_at' => now()]);

        $this->line(json_encode(['consumed' => $affected], JSON_THROW_ON_ERROR));
    }

    /**
     * Print the persisted grants for the user without exposing any digest.
     */
    private function listGrants(User $user): void
    {
        $grants = AuthenticationBootstrap::query()
            ->where('user_id', $user->getKey())
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (AuthenticationBootstrap $grant): array => [
                'purpose' => $grant->purpose->value,
                'active' => $grant->isActive(),
                'consumed' => $grant->consumed_at !== null,
                'expires_at' => $grant->expires_at?->toIso8601String(),
            ])
            ->all();

        $this->line(json_encode(['grants' => $grants], JSON_THROW_ON_ERROR));
    }

    /**
     * Resolve the purpose argument into the enum.
     */
    private function resolvePurpose(): ?AuthenticationBootstrapPurpose
    {
        return match ((string) $this->option('purpose')) {
            'two_factor', AuthenticationBootstrapPurpose::TwoFactorEnrolment->value => AuthenticationBootstrapPurpose::TwoFactorEnrolment,
            'account_recovery', AuthenticationBootstrapPurpose::AccountRecovery->value => AuthenticationBootstrapPurpose::AccountRecovery,
            default => null,
        };
    }

    /**
     * Build the URL a browser would open for the grant.
     */
    private function url(string $token, AuthenticationBootstrapPurpose $purpose): string
    {
        return $purpose === AuthenticationBootstrapPurpose::AccountRecovery
            ? route('otp.reset', ['token' => $token])
            : route('auth.bootstrap.two-factor.show', ['token' => $token]);
    }
}
