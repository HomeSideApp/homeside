<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Jobs\SyncContactSourceJob;
use App\Models\ContactSource;
use App\Services\Contacts\ContactProviderRegistry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Socialite\Contracts\Provider;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\GoogleProvider;
use Laravel\Socialite\Two\User as SocialiteUser;

class GoogleContactSourceController extends Controller
{
    public function redirect(Request $request): \Symfony\Component\HttpFoundation\RedirectResponse
    {
        Gate::authorize('create', ContactSource::class);
        $request->session()->forget('google.contact_reconnect');

        return $this->provider()->redirect();
    }

    public function reconnect(Request $request, ContactSource $source): \Symfony\Component\HttpFoundation\RedirectResponse
    {
        Gate::authorize('update', $source);
        abort_unless($source->provider === 'google' && $source->user_id === $this->authenticatedUser($request)->id, 404);
        $request->session()->put('google.contact_reconnect', $source->id);

        return $this->provider()->redirect();
    }

    public function callback(Request $request, ContactProviderRegistry $registry): RedirectResponse
    {
        try {
            $google = $this->provider()->user();
            if (! $google instanceof SocialiteUser) {
                throw new \RuntimeException('Unexpected Google OAuth user.');
            }
            $sub = (string) $google->getId();
            $email = (string) $google->getEmail();
            abort_unless($sub !== '' && $email !== '' && ($google->user['email_verified'] ?? false) === true, 422);

            $reconnectId = $request->session()->pull('google.contact_reconnect');
            if (is_string($reconnectId)) {
                $source = ContactSource::query()->findOrFail($reconnectId);
                Gate::authorize('update', $source);
                abort_unless($source->provider === 'google'
                    && $source->user_id === $this->authenticatedUser($request)->id
                    && ($source->provider_configuration['google_sub'] ?? null) === $sub, 403);
                $oldTokens = $source->encrypted_tokens ?? [];
                $refreshToken = $google->refreshToken ?: ($oldTokens['refresh_token'] ?? null);
                abort_unless(is_string($refreshToken) && $refreshToken !== '', 422);
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

            Gate::authorize('create', ContactSource::class);
            if (ContactSource::query()->where('provider', 'google')->where('user_id', $this->authenticatedUser($request)->id)
                ->where('provider_configuration->google_sub', $sub)->exists()) {
                throw ValidationException::withMessages(['google' => 'Esta cuenta Google ya está conectada.']);
            }
            abort_unless(trim($google->refreshToken) !== '', 422);
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

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('create', ContactSource::class);
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'favorite_label' => ['required', 'string', 'max:80'],
            'selected_collections' => ['required', 'array', 'size:1'],
            'selected_collections.0' => ['required', 'in:all'],
        ]);
        $key = $request->session()->get('google.contact_draft');
        $stored = is_string($key) ? Cache::get('google-contact-draft:'.$key) : null;
        $draft = is_string($stored) ? json_decode(Crypt::decryptString($stored), true) : null;
        abort_unless(is_array($draft) && $draft['user_id'] === $this->authenticatedUser($request)->id, 422, 'La conexión Google ha caducado.');

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
