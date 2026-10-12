<?php

namespace App\Http\Controllers;

use App\Http\Requests\ConfirmOtpRequest;
use App\Http\Requests\SignedOtpSetupRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\URL;
use Inertia\Inertia;
use Inertia\Response;
use PragmaRX\Google2FA\Google2FA;

/**
 * Handles the OTP (two-factor) setup flow for users.
 */
class OtpSetupController extends Controller
{
    /**
     * Show the OTP setup page, or send an already-enrolled user to log in.
     *
     * Possessing the signed link is enough to start enrolment, but it is never
     * a session: when 2FA is already configured the user is redirected to the
     * login page to authenticate with their password (or OAuth) and a TOTP
     * code. The signed-route protection stays intact.
     *
     * @param  User  $user  The user the signed link was issued for.
     * @return Response|RedirectResponse The HTTP response.
     */
    public function show(SignedOtpSetupRequest $request, User $user): Response|RedirectResponse
    {
        if ($user->hasEnabledTwoFactorAuthentication()) {
            return to_route('login')->with('status', __('app.errors.two_factor_already_enabled'));
        }

        $google2fa = new Google2FA;
        $secret = $google2fa->generateSecretKey();
        $qrCodeUrl = $google2fa->getQRCodeUrl(
            config('app.name'),
            $user->email,
            $secret
        );

        session(["otp_secret_{$user->id}" => $secret]);

        $confirmUrl = URL::temporarySignedRoute('otp.confirm', now()->addMinutes(30), ['user' => $user->id]);

        return Inertia::render('auth/OtpSetup', [
            'qrCodeUrl' => $qrCodeUrl,
            'secret' => $secret,
            'confirmUrl' => $confirmUrl,
        ]);
    }

    /**
     * Verify the OTP code, finalise the 2FA configuration and finish onboarding.
     *
     * The signed link alone never creates a session: reaching this action only
     * authorises enrolment. A session is granted here, after the TOTP code has
     * been verified, and ONLY when every access requirement is satisfied — an
     * approved account with a verified email. A user who is still pending, or
     * who has not verified their email, finishes enrolment but is sent to log in
     * without being authenticated.
     *
     * @param  ConfirmOtpRequest  $request  The incoming HTTP request.
     * @param  User  $user  The user the signed link was issued for.
     * @return Response|RedirectResponse The HTTP response.
     */
    public function confirm(ConfirmOtpRequest $request, User $user): Response|RedirectResponse
    {
        $secret = $request->session()->get("otp_secret_{$user->id}");

        $recoveryCodes = [];
        for ($i = 0; $i < 8; $i++) {
            $recoveryCodes[] = strtoupper(bin2hex(random_bytes(4)));
        }

        $user->update([
            'two_factor_secret' => encrypt($secret),
            'two_factor_confirmed_at' => now(),
            'two_factor_recovery_codes' => encrypt(json_encode($recoveryCodes)),
        ]);

        session()->forget("otp_secret_{$user->id}");

        if (! $user->canAccessApplication() || ! $user->hasVerifiedEmail()) {
            return to_route('login')->with('status', __('app.errors.account_not_ready'));
        }

        Auth::login($user);
        $request->session()->regenerate();

        return Inertia::render('auth/OtpRecoveryCodes', [
            'recoveryCodes' => $recoveryCodes,
        ]);
    }
}
