<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\PasswordUpdateRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;
use PragmaRX\Google2FA\Google2FA;

/**
 * Handles the user's security settings (password and 2FA).
 */
class SecurityController extends Controller
{
    /**
     * Show the user's security settings page.
     *
     * @param  Request  $request  The incoming HTTP request.
     * @return Response The HTTP response.
     */
    public function edit(Request $request): Response
    {
        $user = $this->authenticatedUser($request);
        $props = [
            'twoFactorEnabled' => $user->hasEnabledTwoFactorAuthentication(),
            'passwordRules' => Password::defaults()->toPasswordRulesString(),
        ];

        return Inertia::render('settings/Security', $props);
    }

    /**
     * Update the user's password.
     *
     * @param  PasswordUpdateRequest  $request  The incoming HTTP request.
     * @return RedirectResponse The HTTP response.
     */
    public function update(PasswordUpdateRequest $request): RedirectResponse
    {
        $user = $this->authenticatedUser($request);
        $user->update([
            'password' => $request->password,
        ]);

        // Revocar tokens de API (Sanctum) excepto el actual (si existe)
        $currentToken = $user->currentAccessToken();
        $user->tokens()
            ->when($currentToken !== null, fn ($query) => $query->where('id', '!=', $currentToken->id))
            ->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('app.security.password_updated')]);

        return back();
    }

    /**
     * Reset the user's OTP configuration and generate new secret + recovery codes.
     *
     * @param  Request  $request  The incoming HTTP request.
     * @return JsonResponse The JSON response.
     */
    public function resetOtp(Request $request): JsonResponse
    {
        $user = $this->authenticatedUser($request);
        $google2fa = new Google2FA;
        $secret = $google2fa->generateSecretKey();
        $qrCodeUrl = $google2fa->getQRCodeUrl(
            config('app.name'),
            $user->email,
            $secret
        );

        $recoveryCodes = collect(range(1, 8))->map(fn () => strtoupper(bin2hex(random_bytes(4))))->toArray();

        $user->update([
            'two_factor_secret' => encrypt($secret),
            'two_factor_recovery_codes' => encrypt(json_encode($recoveryCodes)),
        ]);

        return response()->json([
            'qrCodeUrl' => $qrCodeUrl,
            'secret' => $secret,
            'recoveryCodes' => $recoveryCodes,
        ]);
    }
}
