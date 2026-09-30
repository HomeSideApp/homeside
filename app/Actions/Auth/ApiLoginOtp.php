<?php

namespace App\Actions\Auth;

use App\Models\User;
use PragmaRX\Google2FA\Google2FA;

/**
 * Completes an API login by verifying the OTP code and issuing a
 * long-lived authentication token.
 */
final class ApiLoginOtp
{
    /**
     * @param  User  $user  The user to authenticate
     * @param  string  $code  The six-digit OTP code
     * @return array{token: string, user: array{id: string, name: string, email: string}}
     */
    public function execute(User $user, string $code): array
    {
        abort_unless($user->canAccessApplication(), 403, 'Tu cuenta no está aprobada.');

        if ($user->two_factor_secret === null) {
            abort(422, 'La autenticación OTP no está configurada.');
        }

        $google2fa = new Google2FA;
        $valid = $google2fa->verifyKey(
            decrypt($user->two_factor_secret),
            $code
        );

        if (! $valid) {
            abort(422, 'El código OTP es inválido.');
        }

        // Eliminar token temporal
        $user->tokens()->where('name', 'temp-otp')->delete();

        // Crear token de larga duración
        $token = $user->createToken('auth-token');

        return [
            'token' => $token->plainTextToken,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ],
        ];
    }
}
