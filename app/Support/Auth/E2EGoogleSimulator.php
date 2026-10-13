<?php

namespace App\Support\Auth;

use Illuminate\Support\Facades\Log;

/**
 * Decides whether the simulated Google provider may be used.
 *
 * This is the single fail-closed gate for pointing Socialite at the local
 * simulator. It is deliberately strict and independent: ALL of the following
 * must hold, and a production install that somehow exports the flag still
 * fails this check and keeps talking to Google.
 *
 *  1. the `e2e.google_simulator.enabled` flag is set (a hint, never the authority);
 *  2. the application environment is in the E2E allow-list (`e2e` only -- the
 *     PHPUnit `testing` environment is NOT in it, so the suite keeps its
 *     `Socialite::fake` path);
 *  3. the simulator URL is a loopback address, so even inside E2E the exchange
 *     can never be redirected to an arbitrary host;
 *  4. the Google client credentials and callback are configured, because the
 *     provider and the simulator must agree on them.
 *
 * The alternative -- refusing to boot -- was rejected on purpose: a stray
 * environment variable must not take a real install down, and the safe
 * direction is always "use the real provider". A warning is logged once so the
 * misconfiguration is visible instead of silent.
 */
final class E2EGoogleSimulator
{
    /** Whether the "ignored outside E2E" warning has already been emitted this process. */
    private static bool $warned = false;

    /**
     * Determine whether the simulated provider may be selected.
     *
     * @return bool True only inside the E2E environment, with a loopback URL and configured credentials.
     */
    public function enabled(): bool
    {
        if (! $this->flagEnabled()) {
            return false;
        }

        if (! $this->environmentEnabled()) {
            $this->warn(sprintf(
                'Google simulator requested while APP_ENV is "%s"; ignoring it and talking to Google.',
                app()->environment(),
            ));

            return false;
        }

        if (! $this->isLoopback($this->baseUrl())) {
            $this->warn(sprintf(
                'Google simulator URL [%s] is not a loopback address; refusing it.',
                $this->baseUrl(),
            ));

            return false;
        }

        return filled(config('services.google.client_id'))
            && filled(config('services.google.client_secret'))
            && filled(config('services.google.redirect'));
    }

    /**
     * Determine whether the explanatory flag is set.
     *
     * @return bool True when the environment asks for the simulator.
     */
    public function flagEnabled(): bool
    {
        return (bool) config('e2e.google_simulator.enabled', false);
    }

    /**
     * Determine whether the current environment is allowed to use the simulator.
     *
     * @return bool True inside the E2E allow-list.
     */
    public function environmentEnabled(): bool
    {
        return app()->environment((array) config('e2e.google_simulator.environments', ['e2e']));
    }

    /**
     * The simulator base URL without a trailing slash.
     *
     * @return string The base URL.
     */
    public function baseUrl(): string
    {
        return rtrim((string) config('e2e.google_simulator.base_url', ''), '/');
    }

    /**
     * Determine whether a URL points at the loopback interface.
     *
     * @param  string  $url  The URL to inspect.
     * @return bool True for 127.0.0.0/8, ::1 and localhost.
     */
    private function isLoopback(string $url): bool
    {
        $host = parse_url($url, PHP_URL_HOST);

        if (! is_string($host) || $host === '') {
            return false;
        }

        if (strcasecmp($host, 'localhost') === 0 || strcasecmp($host, '::1') === 0) {
            return true;
        }

        return filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false
            && str_starts_with($host, '127.');
    }

    /**
     * Emit the fail-closed warning exactly once per process.
     *
     * @param  string  $message  The warning to log.
     */
    private function warn(string $message): void
    {
        if (self::$warned) {
            return;
        }

        self::$warned = true;

        Log::warning($message);
    }
}
