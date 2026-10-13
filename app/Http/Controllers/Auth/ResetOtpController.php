<?php

namespace App\Http\Controllers\Auth;

use App\Actions\Auth\CompleteAuthenticationBootstrap;
use App\Enums\AuthenticationBootstrapPurpose;
use App\Http\Controllers\Controller;
use App\Http\Requests\ConfirmResetOtpRequest;
use App\Http\Requests\SignedOtpResetRequest;
use App\Models\AuthenticationBootstrap;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
use PragmaRX\Google2FA\Google2FA;

/**
 * Handles the OTP reset flow with a new QR code.
 *
 * The credential is a single-use `AccountRecovery` bootstrap token, not a
 * temporary signed URL. The old second factor keeps working until the new one
 * is confirmed; the grant is then consumed atomically and the secret replaced.
 *
 * Recovery deliberately does NOT create a session. The link is requested with
 * an email address alone, so establishing a session from it would let anyone
 * with inbox access take over an account without the password. The visitor
 * finishes on the recovery-codes screen and signs in with the new factor.
 */
class ResetOtpController extends Controller
{
    /**
     * Show the reset OTP page with a new QR code.
     *
     * The old OTP data is preserved until the new one is confirmed.
     *
     * @param  SignedOtpResetRequest  $request  The authorised recovery request.
     * @return Response|RedirectResponse The HTTP response.
     */
    public function show(SignedOtpResetRequest $request): Response|RedirectResponse
    {
        $bootstrap = $request->recoveryBootstrap();
        $user = $bootstrap?->user;

        // The grant is purpose-bound and can only be shown for an account that
        // already owns a second factor worth recovering.
        if ($bootstrap === null || $user === null || ! $user->hasEnabledTwoFactorAuthentication()) {
            return $this->invalidState();
        }

        $google2fa = new Google2FA;
        $secret = $google2fa->generateSecretKey();

        $request->session()->put($this->secretKey($bootstrap), $secret);

        return Inertia::render('auth/ResetOtp', [
            'qrCodeUrl' => $google2fa->getQRCodeUrl(config('app.name'), $user->email, $secret),
            'secret' => $secret,
            'confirmUrl' => route('otp.reset.confirm', ['token' => $request->recoveryToken()]),
        ]);
    }

    /**
     * Confirm the new OTP and replace the old one.
     *
     * The presented code is verified BEFORE the single-use grant is consumed,
     * so a mistyped code fails without burning the link and a retry within the
     * TTL still works.
     *
     * @param  ConfirmResetOtpRequest  $request  The authorised recovery request.
     * @param  CompleteAuthenticationBootstrap  $complete  The redemption action.
     * @return Response|RedirectResponse The HTTP response.
     */
    public function confirm(ConfirmResetOtpRequest $request, CompleteAuthenticationBootstrap $complete): Response|RedirectResponse
    {
        $bootstrap = $request->recoveryBootstrap();
        $user = $bootstrap?->user;

        if ($bootstrap === null || $user === null || ! $user->hasEnabledTwoFactorAuthentication()) {
            return $this->invalidState();
        }

        $secret = $request->session()->get($this->secretKey($bootstrap));

        if (! is_string($secret) || $secret === '') {
            return back()->withErrors(['code' => __('app.errors.session_expired')]);
        }

        $consumed = $complete->execute(
            $request->recoveryToken(),
            AuthenticationBootstrapPurpose::AccountRecovery,
            $secret,
            $request->validated('code'),
        );

        if ($consumed === null) {
            // The code was wrong, or a concurrent request consumed the token.
            // A bad code never burns the grant, so the pending secret is kept
            // in the session for a retry.
            return back()->withErrors(['code' => __('app.errors.otp_code_invalid_verify')]);
        }

        $request->session()->forget($this->secretKey($bootstrap));

        $enrolledUser = $consumed->user;

        if ($enrolledUser === null) {
            return $this->invalidState();
        }

        return Inertia::render('auth/OtpRecoveryCodes', [
            'recoveryCodes' => $this->recoveryCodes($enrolledUser),
        ]);
    }

    /**
     * Return the clear "this link is not usable" state without a session.
     *
     * The message is intentionally identical for an unknown, expired and
     * consumed token: it must never reveal whether a user exists.
     *
     * @return RedirectResponse The HTTP response.
     */
    private function invalidState(): RedirectResponse
    {
        return to_route('login')->with('status', __('app.errors.recovery_bootstrap_invalid'));
    }

    /**
     * Build the session key that holds the pending secret for a grant.
     *
     * Keyed by grant id, never by the raw token, so the token is not copied
     * into the session store.
     *
     * @param  AuthenticationBootstrap  $bootstrap  The active recovery grant.
     * @return string The session key.
     */
    private function secretKey(AuthenticationBootstrap $bootstrap): string
    {
        return 'otp_reset_secret_'.$bootstrap->id;
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
