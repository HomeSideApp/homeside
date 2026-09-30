<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\ConfirmResetOtpRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\URL;
use Inertia\Inertia;
use Inertia\Response;
use PragmaRX\Google2FA\Google2FA;

/**
 * Handles the OTP reset flow with a new QR code.
 */
class ResetOtpController extends Controller
{
    /**
     * Show the reset OTP page with new QR code.
     * The old OTP data is preserved until the new one is confirmed.
     *
     * @param  User  $user  The authenticated user.
     * @return Response|RedirectResponse The HTTP response.
     */
    public function show(User $user): Response|RedirectResponse
    {
        abort_unless($user->canAccessApplication(), 403);
        if (! $user->hasEnabledTwoFactorAuthentication()) {
            return back()->withErrors(['error' => __('app.auth.otp_not_configured')]);
        }

        $google2fa = new Google2FA;
        $secret = $google2fa->generateSecretKey();
        $qrCodeUrl = $google2fa->getQRCodeUrl(
            config('app.name'),
            $user->email,
            $secret
        );

        session(["otp_reset_secret_{$user->id}" => $secret]);

        return Inertia::render('auth/ResetOtp', [
            'qrCodeUrl' => $qrCodeUrl,
            'secret' => $secret,
            'confirmUrl' => URL::temporarySignedRoute('otp.reset.confirm', now()->addMinutes(30), ['user' => $user->id]),
        ]);
    }

    /**
     * Confirm the new OTP and replace the old one.
     *
     * @param  ConfirmResetOtpRequest  $request  The incoming HTTP request.
     * @param  User  $user  The authenticated user.
     * @return Response|RedirectResponse The HTTP response.
     */
    public function confirm(ConfirmResetOtpRequest $request, User $user): Response|RedirectResponse
    {
        abort_unless($user->canAccessApplication(), 403);
        $validated = $request->validated();

        $secret = session("otp_reset_secret_{$user->id}");

        if (! $secret) {
            return back()->withErrors(['code' => __('app.auth.reset_session_expired')]);
        }

        $google2fa = new Google2FA;
        $valid = $google2fa->verifyKey($secret, $validated['code']);

        if (! $valid) {
            return back()->withErrors(['code' => __('app.auth.invalid_otp')]);
        }

        // Generar nuevos códigos de recuperación
        $recoveryCodes = [];
        for ($i = 0; $i < 8; $i++) {
            $recoveryCodes[] = strtoupper(bin2hex(random_bytes(4)));
        }

        // Reemplazar el OTP antiguo con el nuevo (los datos viejos se borran AHORA)
        $user->update([
            'two_factor_secret' => encrypt($secret),
            'two_factor_recovery_codes' => encrypt(json_encode($recoveryCodes)),
        ]);

        session()->forget("otp_reset_secret_{$user->id}");

        return Inertia::render('auth/OtpRecoveryCodes', [
            'recoveryCodes' => $recoveryCodes,
        ]);
    }
}
