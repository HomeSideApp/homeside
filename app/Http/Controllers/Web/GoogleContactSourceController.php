<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\CreateContactSourceRequest;
use App\Http\Requests\GoogleContactCallbackRequest;
use App\Http\Requests\ManageGoogleContactSourceRequest;
use App\Http\Requests\StoreGoogleContactSourceRequest;
use App\Http\Requests\UpdateGoogleContactSourceRequest;
use App\Jobs\SyncContactSourceJob;
use App\Models\ContactSource;
use App\Services\Contacts\ContactProviderRegistry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Socialite\Contracts\Provider;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\GoogleProvider;
use Laravel\Socialite\Two\User as SocialiteUser;

class GoogleContactSourceController extends Controller
{
    public function redirect(CreateContactSourceRequest $request): \Symfony\Component\HttpFoundation\RedirectResponse
    {
        $request->session()->forget('google.contact_reconnect');

        return $this->provider()->redirect();
    }

    public function reconnect(ManageGoogleContactSourceRequest $request, ContactSource $source): \Symfony\Component\HttpFoundation\RedirectResponse
    {
        $request->session()->put('google.contact_reconnect', $source->id);

        return $this->provider()->redirect();
    }

    public function callback(GoogleContactCallbackRequest $request, ContactProviderRegistry $registry): RedirectResponse
    {
        try {
            $google = $this->provider()->user();
            if (! $google instanceof SocialiteUser) {
                throw new \RuntimeException('Unexpected Google OAuth user.');
            }
            $sub = (string) $google->getId();
            $email = (string) $google->getEmail();
            $request->validateIdentity($google);

            $reconnectId = $request->session()->pull('google.contact_reconnect');
            if (is_string($reconnectId)) {
                $source = ContactSource::query()->findOrFail($reconnectId);
                $request->authorizeReconnection($source, $sub);
                $oldTokens = $source->encrypted_tokens ?? [];
                $refreshToken = $google->refreshToken ?: ($oldTokens['refresh_token'] ?? null);
                $request->validateRefreshToken($refreshToken);
                $source->update([
                    'encrypted_tokens' => ['access_token' => $google->token, 'refresh_token' => $refreshToken],
                    'token_expires_at' => now()->addSeconds(max(60, $google->expiresIn)),
                    'sync_enabled' => true,
                    'last_sync_status' => null,
                    'last_sync_error' => null,
                ]);
                SyncContactSourceJob::dispatch($source->id);

                return to_route('contacts.sources.index')->with('status', 'Google conectado de nuevo.');
            }

            $request->authorizeNewConnection($sub);
            $request->validateRefreshToken($google->refreshToken);
            $draft = [
                'user_id' => $this->authenticatedUser($request)->id,
                'sub' => $sub,
                'email' => $email,
                'access_token' => $google->token,
                'refresh_token' => $google->refreshToken,
                'expires_in' => max(60, $google->expiresIn),
            ];
            $candidate = new ContactSource([
                'provider' => 'google',
                'user_id' => $draft['user_id'],
                'provider_configuration' => ['google_sub' => $sub, 'google_email' => $email],
                'encrypted_tokens' => ['access_token' => $draft['access_token'], 'refresh_token' => $draft['refresh_token']],
                'token_expires_at' => now()->addSeconds($draft['expires_in']),
            ]);
            $registry->for($candidate)->collections($candidate);
            $key = (string) Str::uuid();
            Cache::put('google-contact-draft:'.$key, Crypt::encryptString(json_encode($draft, JSON_THROW_ON_ERROR)), now()->addMinutes(10));
            $request->session()->put('google.contact_draft', $key);

            return to_route('contacts.sources.index', ['google_draft' => 1]);
        } catch (\Throwable $exception) {
            report($exception);

            return to_route('contacts.sources.index')->withErrors(['google' => 'No se pudo conectar Google Contactos. Comprueba el permiso e inténtalo otra vez.']);
        }
    }

    public function store(StoreGoogleContactSourceRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $key = $request->session()->get('google.contact_draft');
        $draft = $request->draft();

        $source = DB::transaction(function () use ($draft, $validated): ContactSource {
            $source = ContactSource::create([
                'user_id' => $draft['user_id'],
                'provider' => 'google',
                'name' => $validated['name'],
                'authentication_type' => 'oauth2',
                'encrypted_tokens' => [
                    'access_token' => $draft['access_token'],
                    'refresh_token' => $draft['refresh_token'],
                ],
                'token_expires_at' => now()->addSeconds($draft['expires_in']),
                'provider_configuration' => [
                    'google_sub' => $draft['sub'],
                    'google_email' => $draft['email'],
                    'favorite_label' => trim($validated['favorite_label']),
                ],
                'enabled' => true,
                'sync_enabled' => true,
            ]);
            $source->collections()->create([
                'remote_id' => 'all',
                'remote_href' => 'people/me/connections',
                'name' => 'Todos los contactos',
                'read_only' => true,
                'enabled' => true,
            ]);

            return $source;
        });
        Cache::forget('google-contact-draft:'.$key);
        $request->session()->forget('google.contact_draft');
        SyncContactSourceJob::dispatch($source->id);

        return to_route('contacts.sources.index');
    }

    public function update(UpdateGoogleContactSourceRequest $request, ContactSource $source): RedirectResponse
    {
        $validated = $request->validated();

        $configuration = $source->provider_configuration ?? [];
        $favoriteLabel = trim($validated['favorite_label']);
        $requiresSync = $source->favoriteLabel() !== $favoriteLabel
            || (! $source->enabled && $validated['enabled'])
            || (! $source->sync_enabled && $validated['sync_enabled']);
        $configuration['favorite_label'] = $favoriteLabel;
        $source->update([
            'name' => $validated['name'],
            'provider_configuration' => $configuration,
            'enabled' => $validated['enabled'],
            'sync_enabled' => $validated['sync_enabled'],
        ]);

        if ($source->enabled && $source->sync_enabled && $requiresSync) {
            SyncContactSourceJob::dispatch($source->id);
        }

        return to_route('contacts.sources.index');
    }

    private function provider(): Provider
    {
        $provider = Socialite::driver('google');
        if ($provider instanceof GoogleProvider) {
            return $provider
                ->redirectUrl(route('contacts.sources.google.callback'))
                ->scopes(['https://www.googleapis.com/auth/contacts.readonly'])
                ->with(['access_type' => 'offline', 'prompt' => 'consent', 'include_granted_scopes' => 'true']);
        }

        return $provider;
    }
}
