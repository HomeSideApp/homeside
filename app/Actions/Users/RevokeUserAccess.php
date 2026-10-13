<?php

namespace App\Actions\Users;

use App\Contracts\AuthenticationBootstrapRepository;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The shared revocation primitive for account-level access changes.
 *
 * Suspension, blocking, rejection and deletion all funnel through here so a
 * previously valid credential can never survive the decision. It is a domain
 * action: it touches persistence and the cache, never HTTP.
 *
 * What it revokes today:
 *  - every Sanctum personal access token, so a mobile session dies at once;
 *  - the `remember_token`, so a persistent login cookie cannot re-establish a
 *    session;
 *  - every database-backed session row for the user, so an already-open browser
 *    session loses authorization on its next request even before the
 *    EnsureAccountIsActive middleware runs;
 *  - the outstanding cache-backed authentication challenges indexed by user
 *    (OTP reset, two-factor enrolment) and the challenge payload they point to;
 *  - every outstanding single-use authentication bootstrap, whatever its
 *    purpose, so a suspension, rejection or block kills any onboarding or
 *    recovery link already issued.
 *
 * Extension point: any future credential that grants access on its own belongs
 * next to `revokeAuthenticationChallenges()`, so nothing else has to change.
 */
final class RevokeUserAccess
{
    public function __construct(private AuthenticationBootstrapRepository $bootstraps) {}

    /**
     * Revoke every outstanding credential and challenge for the user.
     *
     * Safe to call repeatedly and safe to call inside a surrounding
     * transaction: it performs no irreversible external side effect.
     *
     * @param  User  $user  The user whose access is being revoked.
     */
    public function execute(User $user): void
    {
        $this->revokeApiTokens($user);
        $this->revokeRememberToken($user);
        $this->invalidateSessions($user);
        $this->revokeAuthenticationChallenges($user);
        $this->revokeAuthenticationBootstraps($user);
    }

    /**
     * Delete every Sanctum personal access token.
     *
     * An opaque API token carries no session state, so a revocation has to
     * delete the rows; rotating a secret is not enough.
     *
     * @param  User  $user  The user whose tokens are revoked.
     */
    private function revokeApiTokens(User $user): void
    {
        $user->tokens()->delete();
    }

    /**
     * Clear the remember-me token.
     *
     * A remembered login can recreate a session without a password, so leaving
     * it in place would defeat the revocation.
     *
     * @param  User  $user  The user whose remember token is revoked.
     */
    private function revokeRememberToken(User $user): void
    {
        if ($user->remember_token === null) {
            return;
        }

        $user->forceFill(['remember_token' => null])->saveQuietly();
    }

    /**
     * Delete the user's rows from the database-backed session store.
     *
     * Only the database driver keeps a `user_id` index, which is the only
     * portable way to target *every* session of a user rather than the current
     * one. For the other drivers the request-scoped middleware is the
     * enforcement point; there is no framework API to purge a user's sessions
     * from Redis or files.
     *
     * @param  User  $user  The user whose sessions are invalidated.
     */
    private function invalidateSessions(User $user): void
    {
        if (config('session.driver') !== 'database') {
            return;
        }

        if (! Schema::hasTable('sessions')) {
            return;
        }

        DB::table('sessions')->where('user_id', $user->id)->delete();
    }

    /**
     * Forget the outstanding authentication challenges that belong to the user.
     *
     * The API OTP-reset and two-factor enrolment flows store a challenge id in
     * the cache and index it by user id. Both the index entry and the challenge
     * payload are removed, so a previously issued challenge cannot be redeemed
     * after a block or a rejection. Outstanding single-use authentication
     * bootstraps are deleted in the same pass.
     *
     * @param  User  $user  The user whose challenges are revoked.
     */
    private function revokeAuthenticationChallenges(User $user): void
    {
        $this->forgetIndexedChallenge('public-otp-reset-user:'.$user->id, 'public-otp-reset:');
        $this->forgetIndexedChallenge('two-factor-challenge-user:'.$user->id, 'two-factor-challenge:');
    }

    /**
     * Delete every outstanding single-use authentication bootstrap for the user.
     *
     * A bootstrap is a bearer credential that grants enrolment (or recovery)
     * without a session. Revocation must therefore delete the rows rather than
     * rotate a secret: a link mailed before a block must stop working the
     * instant the block lands, whatever its purpose.
     *
     * @param  User  $user  The user whose bootstraps are revoked.
     */
    private function revokeAuthenticationBootstraps(User $user): void
    {
        $this->bootstraps->invalidateAllFor($user);
    }

    /**
     * Forget a user-indexed challenge id and the challenge payload it points at.
     *
     * @param  string  $indexKey  The cache key that stores the current challenge id.
     * @param  string  $challengePrefix  The prefix of the challenge payload key.
     */
    private function forgetIndexedChallenge(string $indexKey, string $challengePrefix): void
    {
        $challengeId = Cache::pull($indexKey);

        if (is_string($challengeId) && $challengeId !== '') {
            Cache::forget($challengePrefix.$challengeId);
        }
    }
}
