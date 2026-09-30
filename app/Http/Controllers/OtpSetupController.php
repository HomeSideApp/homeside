<?php

namespace App\Http\Controllers;

use App\Http\Requests\ConfirmOtpRequest;
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
     * Show the OTP setup page or redirect when 2FA is already configured.
     *
     * @param  User  $user  The authenticated user.
     * @return Response|RedirectResponse The HTTP response.
     */
    public function show(User $user): Response|RedirectResponse
    {
        abort_unless($user->canAccessApplication(), 403);
        if ($user->two_factor_secret && $user->two_factor_confirmed_at) {
            Auth::login($user);

            return to_route('dashboard');
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
     * Verify the OTP code and finalise the 2FA configuration.
     *
     * @param  ConfirmOtpRequest  $request  The incoming HTTP request.
     * @param  User  $user  The authenticated user.
     * @return Response|RedirectResponse The HTTP response.
     */
    public function confirm(ConfirmOtpRequest $request, User $user): Response|RedirectResponse
    {
        abort_unless($user->canAccessApplication(), 403);
        $validated = $request->validated();

        $secret = session("otp_secret_{$user->id}");

        if (! $secret) {
            return back()->withErrors(['code' => __('app.errors.session_expired')]);
        }

        $google2fa = new Google2FA;
        $valid = $google2fa->verifyKey($secret, $validated['code']);

        if (! $valid) {
            return back()->withErrors(['code' => __('app.errors.otp_code_invalid_verify')]);
        }

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

        Auth::login($user);

        return Inertia::render('auth/OtpRecoveryCodes', [
            'recoveryCodes' => $recoveryCodes,
        ]);
    }
}
