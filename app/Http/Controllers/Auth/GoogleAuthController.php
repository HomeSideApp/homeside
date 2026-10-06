<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\ConfirmGoogleOtpSetupRequest;
use App\Http\Requests\GoogleLoginCallbackRequest;
use App\Http\Requests\ShowGoogleOtpSetupRequest;
use App\Services\Auth\GoogleIdentityService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Socialite\Facades\Socialite;
use PragmaRX\Google2FA\Google2FA;

class GoogleAuthController extends Controller
{
    public function redirect(): \Symfony\Component\HttpFoundation\RedirectResponse
    {
        return Socialite::driver('google')->redirect();
    }

    public function callback(GoogleLoginCallbackRequest $request, GoogleIdentityService $identities): RedirectResponse
    {
        try {
            $google = Socialite::driver('google')->user();
            $result = $identities->resolve(
                (string) $google->getId(),
                (string) $google->getEmail(),
                (string) $google->getName(),
                ($google->user['email_verified'] ?? false) === true,
            );
        } catch (\Throwable $exception) {
            report($exception);

            return to_route('login')->withErrors(['google' => 'No se pudo comprobar la cuenta Google.']);
        }

        if ($result['status'] === 'link_required') {
            return to_route('login')->withErrors(['google' => 'Ya existe una cuenta con este correo. Entra con tu contraseña y vincula Google en tu perfil.']);
        }
        if ($result['status'] === 'pending' || $result['status'] === 'rejected') {
            return to_route('login')->with('status', $result['status'] === 'pending'
                ? 'Tu cuenta se ha creado correctamente y está pendiente de aprobación por un administrador.'
                : 'Tu solicitud de acceso ha sido rechazada.');
        }

        $user = $result['user'];
        if (! $request->canLogin($user)) {
            return to_route('login')->withErrors(['google' => 'Tu cuenta no tiene un rol asignado.']);
        }

        $request->session()->invalidate();
        $request->session()->regenerateToken();
        $request->session()->put($user->hasEnabledTwoFactorAuthentication() ? 'login.id' : 'google.setup_id', $user->id);

        return $user->hasEnabledTwoFactorAuthentication()
            ? to_route('two-factor.login')
            : to_route('google.otp.setup');
    }

    public function showOtpSetup(ShowGoogleOtpSetupRequest $request): Response|RedirectResponse
    {
        $user = $request->setupUser();

        $secret = (new Google2FA)->generateSecretKey();
        $request->session()->put('google.otp_secret', $secret);

        return Inertia::render('auth/OtpSetup', [
            'qrCodeUrl' => (new Google2FA)->getQRCodeUrl(config('app.name'), $user->email, $secret),
            'secret' => $secret,
            'confirmUrl' => route('google.otp.confirm'),
        ]);
    }

    public function confirmOtpSetup(ConfirmGoogleOtpSetupRequest $request): Response|RedirectResponse
    {
        $user = $request->setupUser();
        $secret = $request->session()->get('google.otp_secret');

        $codes = collect(range(1, 8))->map(fn (): string => strtoupper(bin2hex(random_bytes(4))))->all();
        $user->update([
            'two_factor_secret' => encrypt($secret),
            'two_factor_confirmed_at' => now(),
            'two_factor_recovery_codes' => encrypt(json_encode($codes, JSON_THROW_ON_ERROR)),
        ]);
        $request->session()->forget(['google.setup_id', 'google.otp_secret']);
        Auth::login($user);
        $request->session()->regenerate();

        return Inertia::render('auth/OtpRecoveryCodes', ['recoveryCodes' => $codes]);
    }
}
