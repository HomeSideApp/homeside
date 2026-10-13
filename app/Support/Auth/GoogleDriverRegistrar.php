<?php

namespace App\Support\Auth;

use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\GoogleProvider;

/**
 * Registers the Google driver, swapping only its endpoints inside E2E.
 *
 * Registration is conditional: outside the E2E environment nothing is
 * registered at all, so the production driver stays exactly the framework's
 * `createGoogleDriver()`. The custom creator also re-checks the gate when the
 * driver is actually resolved, so a driver that was registered during an E2E
 * bootstrap still cannot be used after the environment changes.
 *
 * `realProvider()` deliberately mirrors `SocialiteManager::createGoogleDriver()`
 * (`buildProvider(GoogleProvider::class, config('services.google'))`) so the
 * fallback is byte-for-byte the production path.
 */
final class GoogleDriverRegistrar
{
    public function __construct(private E2EGoogleSimulator $simulator) {}

    /**
     * Register the Google driver override, when (and only when) the simulator is allowed.
     */
    public function register(): void
    {
        if (! $this->simulator->enabled()) {
            return;
        }

        Socialite::extend('google', static fn ($container): GoogleProvider => $container->make(GoogleDriverRegistrar::class)->provider());
    }

    /**
     * Build the Google provider that is correct for the current environment.
     *
     * The gate is re-evaluated here, not just in `register()`, so a stale custom
     * creator can never make a production request talk to the simulator.
     *
     * @return GoogleProvider The real or the simulated provider.
     */
    public function provider(): GoogleProvider
    {
        $config = (array) config('services.google');

        if (! $this->simulator->enabled()) {
            return $this->realProvider($config);
        }

        return (new E2EGoogleProvider(
            request(),
            (string) ($config['client_id'] ?? ''),
            (string) ($config['client_secret'] ?? ''),
            $this->redirectUrl($config),
            [],
            $this->simulator->baseUrl(),
        ))->scopes($config['scopes'] ?? []);
    }

    /**
     * Build the framework's real Google provider from configuration.
     *
     * @param  array<string, mixed>  $config  The `services.google` configuration.
     * @return GoogleProvider The real provider.
     */
    public function realProvider(array $config): GoogleProvider
    {
        return (new GoogleProvider(
            request(),
            (string) ($config['client_id'] ?? ''),
            (string) ($config['client_secret'] ?? ''),
            $this->redirectUrl($config),
            [],
        ))->scopes($config['scopes'] ?? []);
    }

    /**
     * Resolve the callback URL, allowing a relative path.
     *
     * @param  array<string, mixed>  $config  The `services.google` configuration.
     * @return string The absolute callback URL.
     */
    private function redirectUrl(array $config): string
    {
        $redirect = (string) ($config['redirect'] ?? '');

        return Str::startsWith($redirect, '/') ? url($redirect) : $redirect;
    }
}
