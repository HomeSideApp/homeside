<?php

namespace App\Actions\Auth;

use App\Models\User;

/**
 * The single decision for the step after an invited account is completed.
 *
 * Both invitation completion paths — the password form and the Google
 * callback — end by asking this collaborator where to send the person, so the
 * two transports cannot drift apart on a security-relevant redirect.
 *
 * A signed `otp.setup` URL is deliberately NOT used: the grant is bound to the
 * user AND the purpose, lives for the enrolment lifetime (12 hours by default)
 * and is consumed once; issuance also mails the link. When the account already
 * has a second factor there is nothing to enrol, so the person goes to login
 * instead.
 */
final class ResolveEnrolmentUrl
{
    public function __construct(private CreateAuthenticationBootstrap $bootstraps) {}

    /**
     * Resolve the redirect target for a freshly completed account.
     *
     * @param  User  $user  The account that just completed registration.
     * @return string The redirect target.
     */
    public function execute(User $user): string
    {
        if ($user->hasEnabledTwoFactorAuthentication()) {
            return route('login');
        }

        $token = $this->bootstraps->execute($user);

        return route('auth.bootstrap.two-factor.show', ['token' => $token]);
    }
}
