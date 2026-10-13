<?php

namespace App\Http\Controllers\Auth;

use App\Actions\Auth\CompleteAuthenticationBootstrap;
use App\Contracts\AuthenticationBootstrapRepository;
use App\Enums\AuthenticationBootstrapPurpose;
use App\Http\Controllers\Controller;
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
 * purpose, it expires in 30 minutes and it is consumed exactly once by the
 * confirm step.
 *
 * Opening the page never creates a session, whatever the state of the token.
 * A session is established only after the TOTP code is verified AND the account
 * is approved, active and email-verified; otherwise the enrolment still
 * completes and the visitor is sent to log in.
 *
 * The raw token travels in our own URL and in the Inertia prop for the form
 * action, and nowhere else: it is never placed in a third-party URL, never
 * written to a log line and never persisted server-side (only its keyed digest
 * is).
 */
final class AuthenticationBootstrapController extends Controller
{
    /**
     * Show the enrolment page for a valid, active, unconsumed token.
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
        $bootstrap = $bootstraps->findActive($token, AuthenticationBootstrapPurpose::TwoFactorEnrolment);

        if ($bootstrap === null) {
            return $this->invalidState();
        }

        $user = $bootstrap->user;

        if ($user === null) {
            return $this->invalidState();
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
