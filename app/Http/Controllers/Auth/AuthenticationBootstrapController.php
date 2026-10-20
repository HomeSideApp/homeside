<?php

namespace App\Http\Controllers\Auth;

use App\Actions\Auth\CompleteAuthenticationBootstrap;
use App\Actions\Auth\CreateAuthenticationBootstrap;
use App\Contracts\AuthenticationBootstrapRepository;
use App\Enums\AuthenticationBootstrapPurpose;
use App\Http\Controllers\Controller;
use App\Http\Requests\ResendEnrolmentLinkRequest;
use App\Models\AuthenticationBootstrap;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;
use PragmaRX\Google2FA\Google2FA;

/**
 * The web transport for a single-use two-factor bootstrap token.
 *
 * It replaces the retired signed `GET /otp/setup/{user}` pair. The token — not
 * a user id — is the credential, it is bound to the `TwoFactorEnrolment`
 * purpose, it lives as long as `AuthenticationBootstrapPurpose::ttlMinutes()`
 * says (12 hours by default, because the visitor has to find the mail and
 * install an authenticator) and it is consumed exactly once by the confirm
 * step.
 *
 * Three outcomes, and only one of them is offered an action:
 *
 *  - an active, unconsumed token shows the enrolment page;
 *  - a GENUINE but EXPIRED token for an account that still has no second factor
 *    and may use the application shows the resend page. That is the whole point
 *    of the classification below: without it an expired link is a dead end,
 *    because `otp/forgot` refuses accounts that have no factor to recover;
 *  - anything else (unknown, already consumed, superseded by a reissue, an
 *    account that already enrolled, or a blocked/rejected/suspended account)
 *    returns one generic state with no action at all.
 *
 * Opening any of these pages never creates a session, whatever the state of the
 * token. A session is established only after the TOTP code is verified AND the
 * account is approved, active and email-verified; otherwise the enrolment still
 * completes and the visitor is sent to log in.
 *
 * The raw token travels in our own URL and in the Inertia prop for the form
 * action, and nowhere else: it is never placed in a third-party URL, never
 * written to a log line, never rendered on the resend page and never persisted
 * server-side (only its keyed digest is).
 */
final class AuthenticationBootstrapController extends Controller
{
    /**
     * Show the enrolment page, or the resend page when the link genuinely expired.
     *
     * @param  Request  $request  The incoming HTTP request.
     * @param  string  $token  The plaintext bootstrap token.
     * @param  AuthenticationBootstrapRepository  $bootstraps  The bootstrap repository.
     * @return Response|RedirectResponse The HTTP response.
     */
    public function show(
        Request $request,
        string $token,
        AuthenticationBootstrapRepository $bootstraps,
    ): Response|RedirectResponse {
        // The classifying lookup ignores expiry and consumption on purpose; it
        // decides what to SHOW. Nothing here authorises a redemption: the
        // confirm step re-resolves the grant with the authorising lookup.
        $bootstrap = $bootstraps->findAnyState($token, AuthenticationBootstrapPurpose::TwoFactorEnrolment);

        if ($bootstrap === null) {
            return $this->invalidState();
        }

        $user = $bootstrap->user;

        if ($user === null) {
            return $this->invalidState();
        }

        // A spent grant is never a candidate for a resend: the credential was
        // already redeemed, and re-minting it would hand a fresh one to whoever
        // still holds the URL.
        if ($bootstrap->consumed_at !== null) {
            return $this->invalidState();
        }

        if ($this->isGenuinelyExpired($bootstrap)) {
            return $this->expiredState($token, $user);
        }

        if ($user->hasEnabledTwoFactorAuthentication()) {
            return to_route('login')->with('status', __('app.errors.two_factor_already_enabled'));
        }

        $google2fa = new Google2FA;
        $secret = $google2fa->generateSecretKey();

        $request->session()->put($this->secretKey($bootstrap), $secret);

        return Inertia::render('auth/OtpSetup', [
            'qrCodeUrl' => $google2fa->getQRCodeUrl(config('app.name'), $user->email, $secret),
            'secret' => $secret,
            'confirmUrl' => route('auth.bootstrap.two-factor.confirm', ['token' => $token]),
        ]);
    }

    /**
     * Issue and mail a fresh enrolment grant for a genuinely expired link.
     *
     * Eligibility is the same as the page that offers the button: the token had
     * to be genuine and expired, the account must still have no second factor
     * and must be allowed to use the application. Everything else is a silent
     * no-op with the SAME response, so the endpoint never becomes an oracle for
     * "this token existed" or "this account is blocked".
     *
     * A reissue invalidates the previous grant (the issuance action always does
     * that), so the link the visitor could not use stops working the moment a
     * new one is mailed.
     *
     * @param  ResendEnrolmentLinkRequest  $request  The validated request.
     * @param  string  $token  The plaintext bootstrap token that expired.
     * @param  AuthenticationBootstrapRepository  $bootstraps  The bootstrap repository.
     * @param  CreateAuthenticationBootstrap  $create  The single-use bootstrap issuer.
     * @return RedirectResponse The HTTP response.
     */
    public function resend(
        ResendEnrolmentLinkRequest $request,
        string $token,
        AuthenticationBootstrapRepository $bootstraps,
        CreateAuthenticationBootstrap $create,
    ): RedirectResponse {
        $bootstrap = $bootstraps->findAnyState($token, AuthenticationBootstrapPurpose::TwoFactorEnrolment);
        $user = $bootstrap?->user;

        $resendable = $bootstrap !== null
            && $user !== null
            && $this->isGenuinelyExpired($bootstrap)
            && ! $user->hasEnabledTwoFactorAuthentication()
            && $user->canAccessApplication();

        if ($resendable) {
            $create->execute($user, AuthenticationBootstrapPurpose::TwoFactorEnrolment);
        }

        // The same redirect and the same message in every branch: resendable or
        // not, the visitor learns nothing about the token or the account.
        return $this->resendRequested();
    }

    /**
     * Verify the code, consume the token and, when eligible, start the session.
     *
     * @param  Request  $request  The incoming HTTP request.
     * @param  string  $token  The plaintext bootstrap token.
     * @param  AuthenticationBootstrapRepository  $bootstraps  The bootstrap repository.
     * @param  CompleteAuthenticationBootstrap  $complete  The redemption action.
     * @return Response|RedirectResponse The HTTP response.
     */
    public function confirm(
        Request $request,
        string $token,
        AuthenticationBootstrapRepository $bootstraps,
        CompleteAuthenticationBootstrap $complete,
    ): Response|RedirectResponse {
        $validated = $request->validate([
            'code' => ['required', 'string', 'size:6'],
        ]);

        $bootstrap = $bootstraps->findActive($token, AuthenticationBootstrapPurpose::TwoFactorEnrolment);

        if ($bootstrap === null) {
            return $this->invalidState();
        }

        $secret = $request->session()->get($this->secretKey($bootstrap));

        if (! is_string($secret) || $secret === '') {
            return back()->withErrors(['code' => __('app.errors.session_expired')]);
        }

        $consumed = $complete->execute(
            $token,
            AuthenticationBootstrapPurpose::TwoFactorEnrolment,
            $secret,
            $validated['code'],
        );

        if ($consumed === null) {
            // The code was wrong, or a concurrent request consumed the token.
            // Neither burns the grant when it was only a bad code, so the
            // secret is deliberately left in the session for a retry.
            return back()->withErrors(['code' => __('app.errors.otp_code_invalid_verify')]);
        }

        $user = $consumed->user;
        $request->session()->forget($this->secretKey($bootstrap));

        if ($user === null || ! $consumed->mayEstablishSession()) {
            return to_route('login')->with('status', __('app.errors.account_not_ready'));
        }

        Auth::login($user);
        $request->session()->regenerate();

        return Inertia::render('auth/OtpRecoveryCodes', [
            'recoveryCodes' => $this->recoveryCodes($user),
        ]);
    }

    /**
     * Determine whether a grant is genuine, unspent and simply too old.
     *
     * A consumed grant is not "expired": offering to resend it would turn an
     * already-redeemed credential into a fresh one for whoever holds the URL.
     *
     * The check is expressed on `expires_at` directly instead of through
     * `AuthenticationBootstrap::isActive()` because that helper compares the
     * cast attribute against `Illuminate\Support\Carbon`, while the application
     * configures `Date::use(Carbon\CarbonImmutable::class)`; the `instanceof`
     * therefore never matches and the helper reports every grant as inactive.
     *
     * @param  AuthenticationBootstrap  $bootstrap  The grant resolved by the classifying lookup.
     * @return bool True when only the clock, and nothing else, closed the gate.
     */
    private function isGenuinelyExpired(AuthenticationBootstrap $bootstrap): bool
    {
        return $bootstrap->consumed_at === null
            && $bootstrap->expires_at !== null
            && $bootstrap->expires_at->isPast();
    }

    /**
     * Decide what a grant that is no longer active may offer.
     *
     * Only a genuinely expired grant for an account that still needs a second
     * factor AND may use the application is offered a resend. A consumed grant,
     * an account that already enrolled (recovery, not enrolment, is its path) or
     * an account without access all get the generic state, byte for byte the
     * same as an unknown token.
     *
     * @param  string  $token  The plaintext token, used only to build the resend action URL.
     * @param  User  $user  The account the grant belongs to.
     * @return Response|RedirectResponse The HTTP response.
     */
    private function expiredState(string $token, User $user): Response|RedirectResponse
    {
        if ($user->hasEnabledTwoFactorAuthentication() || ! $user->canAccessApplication()) {
            return $this->invalidState();
        }

        return Inertia::render('auth/ResendEnrolmentLink', [
            'mode' => 'expired',
            'resendUrl' => route('auth.bootstrap.two-factor.resend', ['token' => $token]),
            'requestUrl' => null,
        ]);
    }

    /**
     * Return the clear "this link is not usable" state without a session.
     *
     * The message is intentionally identical for an unknown, expired and
     * consumed token: it must not reveal whether a user exists.
     *
     * @return RedirectResponse The HTTP response.
     */
    private function invalidState(): RedirectResponse
    {
        return to_route('login')->with('status', __('app.errors.bootstrap_invalid'));
    }

    /**
     * Acknowledge a resend request without saying whether anything was sent.
     *
     * @return RedirectResponse The HTTP response.
     */
    private function resendRequested(): RedirectResponse
    {
        return to_route('auth.enrolment.resend')->with('status', __('app.auth.enrolment_link_sent'));
    }

    /**
     * Build the session key that holds the pending secret for a grant.
     *
     * Keyed by grant id, never by the raw token, so the token is not copied
     * into the session store.
     *
     * @param  AuthenticationBootstrap  $bootstrap  The active grant.
     * @return string The session key.
     */
    private function secretKey(AuthenticationBootstrap $bootstrap): string
    {
        return 'auth_bootstrap_secret_'.$bootstrap->id;
    }

    /**
     * Read the freshly minted recovery codes for display.
     *
     * @param  User  $user  The enrolled user.
     * @return list<string> The plaintext recovery codes.
     */
    private function recoveryCodes(User $user): array
    {
        if ($user->two_factor_recovery_codes === null) {
            return [];
        }

        return json_decode(decrypt($user->two_factor_recovery_codes), true, flags: JSON_THROW_ON_ERROR);
    }
}
