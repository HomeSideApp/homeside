<?php

namespace App\Actions\Auth;

use App\Models\User;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Rotates an active API token: revokes the current one and issues a fresh token.
 *
 * Accepts the raw bearer token string (extracted from the Authorization header)
 * so we can look up the PersonalAccessToken model directly, independent of
 * Sanctum's TransientToken wrapper.
 */
final class ApiRefreshToken
{
    /**
     * @param  User  $user  The authenticated user (resolved by Sanctum).
     * @param  string  $bearerToken  The full bearer token value (id|random|hash)
     * @return array{token: string, user: array{id: string, name: string, email: string}}
     */
    public function execute(User $user, string $bearerToken): array
    {
        abort_unless($user->canAccessApplication(), 403, 'Tu cuenta no está aprobada.');

        // Extract token ID from the bearer format: "id|random|hash"
        $tokenParts = explode('|', $bearerToken);
        $tokenId = $tokenParts[0] ?? null;

        if ($tokenId !== null) {
            PersonalAccessToken::where('id', $tokenId)->delete();
        }

        // Issue a fresh token
        $newToken = $user->createToken('auth-token');

        return [
            'token' => $newToken->plainTextToken,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ],
        ];
    }
}
