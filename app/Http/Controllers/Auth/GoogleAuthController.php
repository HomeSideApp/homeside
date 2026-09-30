<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Auth\GoogleIdentityService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
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

    public function callback(Request $request, GoogleIdentityService $identities): RedirectResponse
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
                ? 'Tu solicitud está pendiente de aprobación.'
                : 'Tu solicitud de acceso ha sido rechazada.');
        }

        $user = $result['user'];
        if ($user === null || ! $user->canAccessApplication()) {
            return to_route('login')->withErrors(['google' => 'Tu cuenta no tiene un rol asignado.']);
        }

        $request->session()->invalidate();
        $request->session()->regenerateToken();
        $request->session()->put($user->hasEnabledTwoFactorAuthentication() ? 'login.id' : 'google.setup_id', $user->id);

        return $user->hasEnabledTwoFactorAuthentication()
            ? to_route('two-factor.login')
            : to_route('google.otp.setup');
    }

    public function showOtpSetup(Request $request): Response|RedirectResponse
    {
        $userId = $request->session()->get('google.setup_id');
        $user = is_string($userId) ? User::query()->find($userId) : null;
        if ($user === null || ! $user->canAccessApplication() || $user->hasEnabledTwoFactorAuthentication()) {
            return to_route('login');
        }

        $secret = (new Google2FA)->generateSecretKey();
        $request->session()->put('google.otp_secret', $secret);

        return Inertia::render('auth/OtpSetup', [
            'qrCodeUrl' => (new Google2FA)->getQRCodeUrl(config('app.name'), $user->email, $secret),
            'secret' => $secret,
            'confirmUrl' => route('google.otp.confirm'),
        ]);
    }

    public function confirmOtpSetup(Request $request): Response|RedirectResponse
    {
        $request->validate(['code' => ['required', 'digits:6']]);
        $userId = $request->session()->get('google.setup_id');
        $secret = $request->session()->get('google.otp_secret');
        $user = is_string($userId) ? User::query()->find($userId) : null;
        if ($user === null || ! $user->canAccessApplication() || ! is_string($secret)) {
            return to_route('login');
        }
        if (! (new Google2FA)->verifyKey($secret, $request->string('code')->toString())) {
            throw ValidationException::withMessages(['code' => 'Código OTP incorrecto.']);
        }

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
