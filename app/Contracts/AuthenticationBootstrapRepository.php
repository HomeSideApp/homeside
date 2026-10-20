<?php

namespace App\Contracts;

use App\Enums\AuthenticationBootstrapPurpose;
use App\Models\AuthenticationBootstrap;
use App\Models\User;

/**
 * The persistence port for single-use authentication bootstraps.
 *
 * The transport never invents or interprets a token: it hands a raw token and
 * a purpose to this port and learns only whether the grant is (or was) valid.
 * The implementation is responsible for hashing the token, for the atomic
 * single-use consumption and for never logging or returning the digest.
 */
interface AuthenticationBootstrapRepository
{
    /**
     * Issue a fresh grant and return its plaintext token exactly once.
     *
     * The plaintext token is never persisted: only its keyed digest is. The
     * caller is responsible for placing it in the response or the redirect and
     * must not log it.
     *
     * @param  User  $user  The user the grant is issued for.
     * @param  AuthenticationBootstrapPurpose  $purpose  The grant's deliberate purpose.
     * @param  int  $ttlMinutes  How long the grant stays redeemable.
     * @return string The base64url-encoded plaintext token.
     */
    public function issue(User $user, AuthenticationBootstrapPurpose $purpose, int $ttlMinutes = 30): string;

    /**
     * Atomically consume a grant, succeeding for at most one caller.
     *
     * Implementations MUST use a conditional update on `consumed_at IS NULL`
     * (and an unexpired `expires_at`) so two simultaneous requests can never
     * both succeed.
     *
     * @param  string  $rawToken  The token as presented by the client.
     * @param  AuthenticationBootstrapPurpose  $purpose  The purpose the token must hold.
     * @return AuthenticationBootstrap|null The consumed grant, or null when it was invalid, expired, already consumed or issued for another purpose.
     */
    public function consume(string $rawToken, AuthenticationBootstrapPurpose $purpose): ?AuthenticationBootstrap;

    /**
     * Resolve an active grant without consuming it, for a pre-check.
     *
     * This is the AUTHORISING lookup: it filters on `consumed_at IS NULL` and
     * an unexpired `expires_at`, so an expired, consumed or unknown token all
     * collapse into `null`. Use it whenever the answer decides whether a
     * credential may be redeemed.
     *
     * @param  string  $rawToken  The token as presented by the client.
     * @param  AuthenticationBootstrapPurpose  $purpose  The purpose the token must hold.
     * @return AuthenticationBootstrap|null The active grant, or null when none matches.
     */
    public function findActive(string $rawToken, AuthenticationBootstrapPurpose $purpose): ?AuthenticationBootstrap;

    /**
     * Resolve a grant of this purpose whatever its state, without consuming it.
     *
     * This is the CLASSIFYING lookup, and it must never authorise anything: it
     * ignores expiry and consumption on purpose so a transport can tell
     * `expired but genuine` apart from `unknown` and from `already used`, and
     * therefore decide what to show. A grant it returns may be expired or
     * spent; only {@see self::findActive()} and {@see self::consume()} may
     * authorise a redemption. A grant that a reissue deleted is unknown here,
     * which is exactly what a superseded link should look like.
     *
     * @param  string  $rawToken  The token as presented by the client.
     * @param  AuthenticationBootstrapPurpose  $purpose  The purpose the token must hold.
     * @return AuthenticationBootstrap|null The grant in any state, or null when the token was never issued for this purpose.
     */
    public function findAnyState(string $rawToken, AuthenticationBootstrapPurpose $purpose): ?AuthenticationBootstrap;

    /**
     * Invalidate every outstanding grant for one user and purpose.
     *
     * Used when regenerating a grant so the previous one dies at the moment a
     * new one is issued.
     *
     * @param  User  $user  The user whose grants are invalidated.
     * @param  AuthenticationBootstrapPurpose  $purpose  The purpose to invalidate.
     */
    public function invalidateFor(User $user, AuthenticationBootstrapPurpose $purpose): void;

    /**
     * Invalidate every outstanding grant for one user, whatever the purpose.
     *
     * Used by access revocation (suspension, blocking, rejection, deletion) so
     * a block kills any link already in flight.
     *
     * @param  User  $user  The user whose grants are invalidated.
     */
    public function invalidateAllFor(User $user): void;
}
