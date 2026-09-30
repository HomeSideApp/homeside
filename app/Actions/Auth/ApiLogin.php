<?php

namespace App\Actions\Auth;

use App\Models\User;
use Illuminate\Support\Facades\Hash;

/**
 * Authenticates a user with email and password, returning a short-lived
 * temporary token when OTP verification is required.
 */
final class ApiLogin
{
    /**
     * @param  string  $email  The user's email
     * @param  string  $password  The user's password
     * @return array{requires_otp: bool, token: string|null, temp_token: string|null, user: array<string, mixed>|null}
     */
    public function execute(string $email, string $password): array
    {
        $user = User::where('email', $email)->first();

        if (! $user || ! Hash::check($password, $user->password)) {
            abort(401, 'Credenciales incorrectas.');
        }

        abort_unless($user->canAccessApplication(), 403, 'Tu cuenta no está aprobada.');

        if (! $user->hasEnabledTwoFactorAuthentication()) {
            abort(403, 'Tu cuenta no tiene autenticación de dos factores configurada. Contacta con un administrador.');
        }

        // Crear token temporal de corta duración para OTP (5 minutos)
        $tempToken = $user->createToken('temp-otp', ['otp-verify'], now()->addMinutes(5));

        return [
            'requires_otp' => true,
            'temp_token' => $tempToken->plainTextToken,
            'token' => null,
            'user' => null,
        ];
    }
}
