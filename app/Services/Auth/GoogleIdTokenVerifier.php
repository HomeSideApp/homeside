<?php

namespace App\Services\Auth;

use Google\Auth\AccessToken;
use Illuminate\Validation\ValidationException;

class GoogleIdTokenVerifier
{
    public function __construct(private AccessToken $accessToken) {}

    /** @return array{sub: string, email: string, name: string, email_verified: bool} */
    public function verify(string $idToken): array
    {
        $clientIds = array_values(array_filter([
            config('services.google.client_id'),
            ...config('services.google.mobile_client_ids', []),
        ]));

        if ($clientIds === []) {
            throw ValidationException::withMessages(['id_token' => 'Google no está configurado.']);
        }

        try {
            $claims = $this->accessToken->verify($idToken);
        } catch (\Throwable) {
            $claims = false;
        }

        if (! is_array($claims)
            || ! in_array($claims['aud'] ?? null, $clientIds, true)
            || ! in_array($claims['iss'] ?? null, ['accounts.google.com', 'https://accounts.google.com'], true)
            || ! is_string($claims['sub'] ?? null)
            || ! is_string($claims['email'] ?? null)
            || ($claims['email_verified'] ?? false) !== true) {
            throw ValidationException::withMessages(['id_token' => 'El token de Google no es válido.']);
        }

        return [
            'sub' => $claims['sub'],
            'email' => $claims['email'],
            'name' => is_string($claims['name'] ?? null) ? $claims['name'] : $claims['email'],
            'email_verified' => true,
        ];
    }
}
