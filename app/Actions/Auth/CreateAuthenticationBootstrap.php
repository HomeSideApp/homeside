<?php

namespace App\Actions\Auth;

use App\Contracts\AuthenticationBootstrapRepository;
use App\Enums\AuthenticationBootstrapPurpose;
use App\Models\User;
use App\Notifications\EnrolmentLinkNotification;

/**
 * Issues the single-use grant that starts a two-factor enrolment (or a
 * recovery) without a session.
 *
 * It is a domain action: the transport decides where the token goes (a redirect
 * for the web, a JSON body for the API). The token is returned in plaintext
 * exactly once and never persisted.
 *
 * One issuance path
 * -----------------
 * Registration, invitation, the household invite link and both recovery flows
 * all go through `execute()`. That has two consequences worth stating:
 *
 *  - the lifetime is resolved per purpose (see
 *    {@see AuthenticationBootstrapPurpose::ttlMinutes()}); enrolment lives
 *    hours because the person has to find the mail, recovery lives minutes
 *    because it can replace the factor;
 *  - the mailed link is part of issuance, not a separate step each caller has
 *    to remember. A registration that never mails the link is precisely the
 *    dead end this action closes: the visitor who closes the tab would have no
 *    way back. A caller that supplies its own delivery channel can still opt
 *    out with `$notify: false`.
 *
 * The mobile API deliberately does NOT opt out: it returns the token in the
 * response body AND mails the same grant. The body is ephemeral (no browser
 * history to come back to, and a client can drop the response), and a mobile
 * user has no `otp/forgot` path because that flow requires an existing second
 * factor. Mailing here adds no second credential: it is the very same
 * single-use grant, so "one live link per user and purpose" still holds, and no
 * secret is exposed that the response body did not already carry.
 *
 * When it is mailed
 * -----------------
 * The mail is dispatched at the end of `execute()`, after the grant row has
 * been written. The action opens no transaction of its own: `issue()` is a
 * single INSERT committed by autocommit, and every call site invokes this
 * action after its own transaction has returned (registration commits inside
 * its use case before the transport asks for a token). A mail is therefore
 * never sent for a grant that could still be rolled back, and a transport never
 * has to remember to "send after commit" on its own.
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
     * The historical single lifetime, in minutes.
     *
     * @deprecated Every caller must use the purpose-aware lifetime.
     *             {@see AuthenticationBootstrapPurpose::ttlMinutes()} is the
     *             single source of truth. This constant only remains so the two
     *             callers outside this action (the mobile API response and the
     *             E2E preparation command) keep compiling; it mirrors the
     *             shipped enrolment default and must never be used to decide how
     *             long a grant lives.
     */
    public const TTL_MINUTES = 720;

    public function __construct(private AuthenticationBootstrapRepository $bootstraps) {}

    /**
     * Issue a grant for the user and return its plaintext token once.
     *
     * @param  User  $user  The user the grant authorises.
     * @param  AuthenticationBootstrapPurpose  $purpose  The grant's deliberate purpose.
     * @param  bool  $notify  Whether to mail the link to an enrolment grant's owner.
     * @return string The plaintext token, never persisted.
     */
    public function execute(
        User $user,
        AuthenticationBootstrapPurpose $purpose = AuthenticationBootstrapPurpose::TwoFactorEnrolment,
        bool $notify = true,
    ): string {
        $this->bootstraps->invalidateAllFor($user);

        $token = $this->bootstraps->issue($user, $purpose, $purpose->ttlMinutes());

        if ($notify) {
            $this->notify($user, $purpose, $token);
        }

        return $token;
    }

    /**
     * Mail the link for an enrolment grant.
     *
     * Recovery keeps its own notification (`ResetOtpNotification`, sent by the
     * forgot-OTP transport) because recovery mails a different promise: the
     * current factor keeps working until a new one is confirmed. Notifying both
     * from here would send two mails for one grant.
     *
     * @param  User  $user  The user the grant was issued for.
     * @param  AuthenticationBootstrapPurpose  $purpose  The grant's purpose.
     * @param  string  $token  The plaintext token, used only to build the URL.
     */
    private function notify(User $user, AuthenticationBootstrapPurpose $purpose, string $token): void
    {
        if ($purpose !== AuthenticationBootstrapPurpose::TwoFactorEnrolment) {
            return;
        }

        $user->notify(new EnrolmentLinkNotification(
            $user,
            route('auth.bootstrap.two-factor.show', ['token' => $token]),
            $purpose->ttlMinutes(),
        ));
    }
}
