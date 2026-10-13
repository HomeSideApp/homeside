<?php

namespace App\Actions\Auth;

use App\Contracts\AuthenticationBootstrapRepository;
use App\Enums\AuthenticationBootstrapPurpose;
use App\Models\AuthenticationBootstrap;
use App\Models\User;
use PragmaRX\Google2FA\Google2FA;

/**
 * Redeems a single-use authentication bootstrap.
 *
 * The order is deliberate:
 *  1. resolve the grant for the presented purpose; a recovery grant is never
 *     found under the enrolment purpose (and vice versa);
 *  2. verify the TOTP code against the pending secret BEFORE consuming, so a
 *     mistyped code fails without burning the grant and a retry within the TTL
 *     still works;
 *  3. consume atomically (`consumed_at IS NULL`); if a concurrent request won
 *     the race, fail without touching the account;
 *  4. persist the confirmed secret and its recovery codes.
 *
 * It is a domain action: it never establishes a session and never knows about
 * HTTP. Whether a session may follow is a property of the consumed grant
 * (`AuthenticationBootstrap::mayEstablishSession()`), which every transport
 * must honour. A pending, rejected, suspended or blocked account can therefore
 * complete enrolment and still be signed in by nobody.
 */
final class CompleteAuthenticationBootstrap
{
    /**
     * The number of recovery codes minted on a successful completion.
     */
    public const RECOVERY_CODE_COUNT = 8;

    public function __construct(private AuthenticationBootstrapRepository $bootstraps) {}

    /**
     * Verify the code and, on success, consume the grant and store the secret.
     *
     * @param  string  $rawToken  The token as presented by the client.
     * @param  AuthenticationBootstrapPurpose  $purpose  The purpose the token must hold.
     * @param  string  $secret  The pending TOTP secret the code was generated from.
     * @param  string  $code  The six-digit TOTP code.
     * @return AuthenticationBootstrap|null The consumed grant, or null when the token, purpose, code or race check failed.
     */
    public function execute(
        string $rawToken,
        AuthenticationBootstrapPurpose $purpose,
        string $secret,
        string $code,
    ): ?AuthenticationBootstrap {
        $bootstrap = $this->bootstraps->findActive($rawToken, $purpose);

        if ($bootstrap === null) {
            return null;
        }

        $user = $bootstrap->user;

        if ($user === null) {
            return null;
        }

        // Enrolment never overwrites an existing second factor; recovery is the
        // only flow allowed to replace it. This is what keeps the two purposes
        // semantically distinct even if a caller mixed them up.
        if ($purpose === AuthenticationBootstrapPurpose::TwoFactorEnrolment
            && $user->hasEnabledTwoFactorAuthentication()) {
            return null;
        }

        if (! (new Google2FA)->verifyKey($secret, $code)) {
            return null;
        }

        $consumed = $this->bootstraps->consume($rawToken, $purpose);

        if ($consumed === null) {
            // Another request consumed the same token between the check and
            // here. The grant is spent; nothing may be written.
            return null;
        }

        $this->persistSecret($user, $secret);

        return $consumed;
    }

    /**
     * Persist the confirmed secret and a fresh set of recovery codes.
     *
     * @param  User  $user  The user being enrolled.
     * @param  string  $secret  The confirmed TOTP secret.
     */
    private function persistSecret(User $user, string $secret): void
    {
        $user->update([
            'two_factor_secret' => encrypt($secret),
            'two_factor_confirmed_at' => now(),
            'two_factor_recovery_codes' => encrypt(json_encode($this->newRecoveryCodes(), JSON_THROW_ON_ERROR)),
        ]);
    }

    /**
     * Mint the one-time recovery codes.
     *
     * @return list<string> The plaintext recovery codes.
     */
    private function newRecoveryCodes(): array
    {
        return collect(range(1, self::RECOVERY_CODE_COUNT))
            ->map(fn (): string => strtoupper(bin2hex(random_bytes(4))))
            ->all();
    }
}
