<?php

namespace Tests\Feature\E2E;

use App\Support\Auth\E2EGoogleProvider;
use App\Support\Auth\E2EGoogleSimulator;
use App\Support\Auth\GoogleDriverRegistrar;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\GoogleProvider;
use Tests\TestCase;

/**
 * The simulated Google provider is a test-only escape hatch, so the gate that
 * selects it is the single most important thing to prove.
 *
 * These tests pin the fail-closed contract:
 *  - it is off by default;
 *  - the flag alone is not enough outside the E2E environment;
 *  - a non-loopback URL is refused even inside E2E;
 *  - inside E2E the driver really is the simulator, and its authorization URL
 *    carries a real `state` and the real callback `redirect_uri`;
 *  - a stale registration still falls back to real Google once the environment
 *    stops being E2E.
 */
class GoogleSimulatorGuardTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.google' => [
                'client_id' => 'e2e-google-client-id',
                'client_secret' => 'e2e-google-client-secret',
                'redirect' => 'http://localhost:8010/auth/google/callback',
            ],
            'e2e.google_simulator' => [
                'enabled' => true,
                'environments' => ['e2e'],
                'host' => '127.0.0.1',
                'port' => 8011,
                'base_url' => 'http://127.0.0.1:8011',
                'router' => base_path('app/Support/Auth/GoogleSimulator/router.php'),
                'state_file' => sys_get_temp_dir().'/homeside-e2e-google-simulator-test.json',
                'pid_file' => sys_get_temp_dir().'/homeside-e2e-google-simulator-test.pid',
                'log_file' => sys_get_temp_dir().'/homeside-e2e-google-simulator-test.log',
            ],
        ]);
    }

    protected function tearDown(): void
    {
        app()['env'] = 'testing';
        Socialite::forgetDrivers();

        parent::tearDown();
    }

    public function test_the_simulator_is_off_unless_the_flag_is_set(): void
    {
        config(['e2e.google_simulator.enabled' => false]);
        app()['env'] = 'e2e';

        $this->assertFalse(app(E2EGoogleSimulator::class)->enabled());
    }

    public function test_the_flag_is_ignored_outside_the_e2e_environment(): void
    {
        foreach (['local', 'staging', 'production'] as $environment) {
            app()['env'] = $environment;
            Socialite::forgetDrivers();

            $this->assertFalse(
                app(E2EGoogleSimulator::class)->enabled(),
                "The simulator was enabled in the [{$environment}] environment.",
            );

            $driver = Socialite::driver('google');

            $this->assertInstanceOf(GoogleProvider::class, $driver);
            $this->assertNotInstanceOf(
                E2EGoogleProvider::class,
                $driver,
                "The [{$environment}] environment received the simulated provider.",
            );
        }
    }

    public function test_a_non_loopback_simulator_url_is_refused(): void
    {
        app()['env'] = 'e2e';

        foreach ([
            'https://accounts.evil.example',
            'http://10.0.0.5:8011',
            'http://0.0.0.0:8011',
            'http://simulator.internal:8011',
            'not-a-url',
        ] as $url) {
            config(['e2e.google_simulator.base_url' => $url]);

            $this->assertFalse(
                app(E2EGoogleSimulator::class)->enabled(),
                "The simulator accepted the non-loopback URL [{$url}].",
            );
        }
    }

    public function test_inside_e2e_the_driver_is_the_simulator_and_keeps_a_real_state(): void
    {
        app()['env'] = 'e2e';
        Socialite::forgetDrivers();

        app(GoogleDriverRegistrar::class)->register();
        Socialite::forgetDrivers();

        // The REAL redirect route, through the real controller and the real
        // provider: only the destination host changes. The generated URL must
        // carry a fresh state and the real callback as the redirect_uri.
        $response = $this->get(route('google.redirect'));
        $response->assertRedirect();

        $this->assertInstanceOf(E2EGoogleProvider::class, Socialite::driver('google'));

        $target = (string) $response->headers->get('Location');
        parse_str((string) parse_url($target, PHP_URL_QUERY), $query);

        $this->assertStringStartsWith('http://127.0.0.1:8011/oauth2/auth?', $target);
        $this->assertSame('http://localhost:8010/auth/google/callback', $query['redirect_uri'] ?? null);
        $this->assertSame('e2e-google-client-id', $query['client_id'] ?? null);
        $this->assertNotEmpty($query['state'] ?? '');
    }

    public function test_a_stale_registration_falls_back_to_real_google_outside_e2e(): void
    {
        app()['env'] = 'e2e';
        Socialite::forgetDrivers();

        app(GoogleDriverRegistrar::class)->register();

        $this->assertInstanceOf(E2EGoogleProvider::class, Socialite::driver('google'));

        // Simulate the same process later booted/observed as production while the
        // custom creator is still registered: the provider must re-check the gate.
        app()['env'] = 'production';
        Socialite::forgetDrivers();

        $driver = Socialite::driver('google');

        $this->assertInstanceOf(GoogleProvider::class, $driver);
        $this->assertNotInstanceOf(E2EGoogleProvider::class, $driver);
    }

    public function test_the_simulator_requires_configured_credentials(): void
    {
        app()['env'] = 'e2e';

        config(['services.google.client_id' => null]);
        $this->assertFalse(app(E2EGoogleSimulator::class)->enabled());

        config([
            'services.google.client_id' => 'e2e-google-client-id',
            'services.google.redirect' => null,
        ]);
        $this->assertFalse(app(E2EGoogleSimulator::class)->enabled());
    }
}
