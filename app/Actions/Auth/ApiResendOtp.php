<?php

namespace App\Actions\Auth;

use App\Models\User;

/**
 * Renews the temporary OTP token for a user that already has an active temp-otp token.
 *
 * The old token is revoked and a fresh one (5-minute TTL) is issued, giving the
 * client more time to enter the OTP code.
 */
final class ApiResendOtp
{
    /**
     * @param  User  $user  The user whose temp token is being renewed.
     * @return array{temp_token: string}
     */
    public function execute(User $user): array
    {
        // Revoke any existing temp-otp tokens
        $user->tokens()->where('name', 'temp-otp')->delete();

        // Issue a fresh temp token (5 minutes to enter the OTP code)
        $tempToken = $user->createToken('temp-otp', ['otp-verify'], now()->addMinutes(5));

        return [
            'temp_token' => $tempToken->plainTextToken,
        ];
    }
}
