<?php

namespace App\Actions\Auth;

use App\Contracts\AuthenticationBootstrapRepository;
use App\Enums\AuthenticationBootstrapPurpose;
use App\Models\User;

/**
 * Issues the single-use grant that starts a two-factor enrolment (or a
 * recovery) without a session.
 *
 * It is a domain action: it returns the plaintext token once and never touches
 * HTTP, Inertia, redirects or headers. The transport decides where the token
 * goes.
 *
 * A fresh grant invalidates every outstanding grant for the user, whatever its
 * purpose. Two deliberate grants for one user at the same time are not wanted:
 * the newest one is the intent, and leaving an older one alive would widen the
 * window and let a stale link win a race. Purpose binding still holds at the
 * credential level, so this invalidation is defence in depth, not the only
 * thing keeping recovery and enrolment apart.
 */
final class CreateAuthenticationBootstrap
{
    /**
     * The lifetime of every grant, in minutes.
     *
     * Deliberately short: the token travels through browser history and email,
     * so the window in which it is useful must be small.
     */
    public const TTL_MINUTES = 30;

    public function __construct(private AuthenticationBootstrapRepository $bootstraps) {}

    /**
     * Issue a grant for the user and return its plaintext token once.
     *
     * @param  User  $user  The user the grant authorises.
     * @param  AuthenticationBootstrapPurpose  $purpose  The grant's deliberate purpose.
     * @return string The plaintext token, never persisted.
     */
    public function execute(
        User $user,
        AuthenticationBootstrapPurpose $purpose = AuthenticationBootstrapPurpose::TwoFactorEnrolment,
    ): string {
        $this->bootstraps->invalidateAllFor($user);

        return $this->bootstraps->issue($user, $purpose, self::TTL_MINUTES);
    }
}
