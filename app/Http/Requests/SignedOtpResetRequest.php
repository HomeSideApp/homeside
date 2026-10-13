<?php

namespace App\Http\Requests;

use App\Contracts\AuthenticationBootstrapRepository;
use App\Enums\AuthenticationBootstrapPurpose;
use App\Models\AuthenticationBootstrap;

/**
 * Base request for the RECOVERY routes (`otp.reset`).
 *
 * The class name is historical: the transport is no longer a temporary signed
 * URL but a single-use `AccountRecovery` bootstrap token carried in the path.
 * The token — not a user id — is the credential, it is stored only as a keyed
 * digest, it expires in 30 minutes and it is consumed exactly once.
 *
 * `authorize()` is the guard that keeps recovery from becoming a second,
 * weaker login: a pending, rejected, suspended or blocked account can never
 * redeem it, whatever the token says. A recovery grant is also purpose-bound,
 * so an enrolment token presented here is simply not found.
 */
class SignedOtpResetRequest extends SignedOtpSetupRequest
{
    /**
     * The grant resolved for the presented token, if any.
     */
    private ?AuthenticationBootstrap $bootstrap = null;

    /**
     * Whether the grant lookup has already run for this request.
     */
    private bool $bootstrapResolved = false;

    /**
     * Authorize the presented recovery token.
     *
     * @return bool True when an active recovery grant exists for an account that may use the application.
     */
    public function authorize(): bool
    {
        $bootstrap = $this->recoveryBootstrap();

        return $bootstrap !== null
            && $bootstrap->user?->canAccessApplication() === true;
    }

    /**
     * Resolve the active recovery grant named by the route token.
     *
     * The lookup is memoised: `authorize()` and the controller ask once per
     * request and never re-hit the database, even though the raw token is
     * never stored.
     *
     * @return AuthenticationBootstrap|null The active grant, or null when the token is missing, expired, consumed or issued for another purpose.
     */
    public function recoveryBootstrap(): ?AuthenticationBootstrap
    {
        if ($this->bootstrapResolved) {
            return $this->bootstrap;
        }

        $this->bootstrapResolved = true;

        $token = $this->route('token');

        if (! is_string($token) || $token === '') {
            return $this->bootstrap = null;
        }

        return $this->bootstrap = app(AuthenticationBootstrapRepository::class)
            ->findActive($token, AuthenticationBootstrapPurpose::AccountRecovery);
    }

    /**
     * Get the raw recovery token from the route.
     *
     * @return string The presented token, or an empty string when absent.
     */
    public function recoveryToken(): string
    {
        $token = $this->route('token');

        return is_string($token) ? $token : '';
    }
}
