<?php

namespace App\Services\Contacts\Providers;

use App\Models\ContactSource;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

final class GooglePeopleClient
{
    /** @param array<string, mixed> $query */
    public function get(ContactSource $source, string $path, array $query = []): Response
    {
        if (! in_array($path, ['people/me/connections', 'contactGroups'], true)) {
            throw new RuntimeException('Unsupported Google People endpoint.');
        }

        $response = $this->send($source, $path, $query);
        if ($response->status() === 401) {
            $this->refresh($source, true);
            $response = $this->send($source, $path, $query);
        }
        if (strlen($response->body()) > 6 * 1024 * 1024) {
            throw new RuntimeException('Google People response exceeded the size limit.');
        }

        return $response;
    }

    /** @return array<string, string> */
    public function groups(ContactSource $source): array
    {
        return Cache::remember('google-contact-groups:'.$source->id, now()->addMinutes(10), function () use ($source): array {
            $names = [];
            $page = null;
            for ($number = 0; $number < 20; $number++) {
                $response = $this->get($source, 'contactGroups', array_filter([
                    'pageSize' => 1000,
                    'pageToken' => $page,
                ]))->throw()->json();
                foreach ($response['contactGroups'] ?? [] as $group) {
                    if (isset($group['resourceName'], $group['name'])) {
                        $names[$group['resourceName']] = $group['name'];
                    }
                }
                $page = $response['nextPageToken'] ?? null;
                if (! is_string($page) || $page === '') {
                    return $names;
                }
            }

            throw new RuntimeException('Too many Google contact groups.');
        });
    }

    public function photo(string $url): ?string
    {
        $parts = parse_url($url);
        $host = strtolower((string) ($parts['host'] ?? ''));
        if (($parts['scheme'] ?? null) !== 'https'
            || ! ($host === 'googleusercontent.com' || str_ends_with($host, '.googleusercontent.com'))
            || isset($parts['user']) || isset($parts['pass'])) {
            return null;
        }

        try {
            $response = Http::connectTimeout(3)->timeout(6)->withOptions(['allow_redirects' => false])->get($url);
            if (! $response->successful() || strlen($response->body()) > 3 * 1024 * 1024) {
                return null;
            }

            return $response->body();
        } catch (\Throwable) {
            return null;
        }
    }

    /** @param array<string, mixed> $query */
    private function send(ContactSource $source, string $path, array $query): Response
    {
        $token = $this->accessToken($source);

        return Http::baseUrl('https://people.googleapis.com/v1/')
            ->connectTimeout(3)->timeout(15)->withOptions(['allow_redirects' => false])
            ->acceptJson()->withToken($token)->get($path, $query);
    }

    private function accessToken(ContactSource $source): string
    {
        $tokens = $source->encrypted_tokens ?? [];
        if ($source->token_expires_at === null || $source->token_expires_at->lessThan(now()->addMinute())) {
            $this->refresh($source);
            $tokens = $source->encrypted_tokens ?? [];
        }

        $token = $tokens['access_token'] ?? null;
        if (! is_string($token) || $token === '') {
            throw new RuntimeException('Google contact source has no access token.');
        }

        return $token;
    }

    private function refresh(ContactSource $source, bool $force = false): void
    {
        Cache::lock('google-token-refresh:'.$source->id, 15)->block(5, function () use ($source, $force): void {
            $source->refresh();
            if (! $force && $source->token_expires_at?->greaterThan(now()->addMinute())) {
                return;
            }

            $tokens = $source->encrypted_tokens ?? [];
            $refreshToken = $tokens['refresh_token'] ?? null;
            if (! is_string($refreshToken) || $refreshToken === '') {
                $this->reconnectRequired($source);
            }

            $response = Http::asForm()->connectTimeout(3)->timeout(10)
                ->withOptions(['allow_redirects' => false])
                ->post('https://oauth2.googleapis.com/token', [
                    'grant_type' => 'refresh_token',
                    'refresh_token' => $refreshToken,
                    'client_id' => config('services.google.client_id'),
                    'client_secret' => config('services.google.client_secret'),
                ]);
            if (! $response->successful()) {
                if ($response->json('error') === 'invalid_grant') {
                    $this->reconnectRequired($source);
                }
                throw new RuntimeException('Google token refresh failed.');
            }

            $newToken = $response->json('access_token');
            if (! is_string($newToken) || $newToken === '') {
                throw new RuntimeException('Google token refresh returned no access token.');
            }
            $source->update([
                'encrypted_tokens' => [
                    'access_token' => $newToken,
                    'refresh_token' => $response->json('refresh_token') ?: $refreshToken,
                ],
                'token_expires_at' => now()->addSeconds((int) $response->json('expires_in', 3600)),
            ]);
        });
    }

    private function reconnectRequired(ContactSource $source): never
    {
        $source->update([
            'sync_enabled' => false,
            'last_sync_status' => 'reconnect_required',
            'last_sync_error' => 'Vuelve a conectar esta cuenta Google.',
        ]);

        throw new RuntimeException('Google contact source requires reconnection.');
    }
}
