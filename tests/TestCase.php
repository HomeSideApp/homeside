<?php

namespace Tests;

use App\Contracts\RegistrationGate;
use App\Contracts\RegistrationPolicyDefaults;
use App\Services\Auth\SelfHostedRegistrationGate;
use App\Services\Auth\SelfHostedRegistrationPolicyDefaults;
use HomeSide\Saas\Services\SaasRegistrationGate;
use HomeSide\Saas\Support\SaasRegistrationPolicyDefaults;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Apply the registration profile selected for this run.
     *
     * Section 13 of the registration spec requires the suite to run in two
     * profiles: an OSS suite that is runnable without the commercial package and
     * a SaaS suite that runs when it is installed. The profile is read from the
     * `HOMESIDE_TEST_PROFILE` environment variable (see phpunit.xml for the
     * documented default):
     *
     *  - `ambient` (the default): leave the container bindings alone. This is
     *    the historical `artisan test` behaviour. Because the app suite must be
     *    green in both profiles, tests that assert a concrete implementation say
     *    so with {@see self::useSelfHostedRegistration()} or
     *    {@see self::useSaasRegistration()} rather than relying on this profile.
     *  - `selfhosted`: force the Community self-hosted implementations, so the
     *    OSS suite keeps self-hosted semantics even when the commercial package
     *    is installed and has already bound its own implementations.
     *  - `saas`: force the commercial implementations when the package is
     *    present. When it is absent the profile leaves the ambient container and
     *    the package-only tests skip themselves.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->applyRegistrationProfile();
    }

    /**
     * The registration profile this suite is running under.
     *
     * Always a lower-case string; an unset or empty variable is `ambient`.
     * Package-specific tests read this to skip cleanly under the wrong profile.
     */
    public static function registrationProfile(): string
    {
        $profile = getenv('HOMESIDE_TEST_PROFILE');

        return is_string($profile) && $profile !== '' ? strtolower($profile) : 'ambient';
    }

    /**
     * Bind the Community self-hosted registration implementations.
     *
     * Call this from a test that asserts the self-hosted defaults, so it names
     * the implementation under test instead of asserting whatever the container
     * happens to hold. When the commercial package is installed its provider
     * binds the SaaS defaults and gate, and an ambient assertion would silently
     * verify the package instead.
     */
    protected function useSelfHostedRegistration(): void
    {
        $this->app->bind(RegistrationPolicyDefaults::class, SelfHostedRegistrationPolicyDefaults::class);
        $this->app->bind(RegistrationGate::class, SelfHostedRegistrationGate::class);
    }

    /**
     * Bind the commercial registration implementations.
     *
     * Skips the test when the package is not installed: the counterpart of the
     * self-hosted profile must never fail merely because OSS is checked out.
     */
    protected function useSaasRegistration(): void
    {
        if (! class_exists(SaasRegistrationPolicyDefaults::class)) {
            $this->markTestSkipped('The homeside/saas package is not installed.');
        }

        $this->app->bind(RegistrationPolicyDefaults::class, SaasRegistrationPolicyDefaults::class);
        $this->app->bind(RegistrationGate::class, SaasRegistrationGate::class);
    }

    /**
     * Apply the forced bindings for the selected profile.
     *
     * `ambient` deliberately does nothing. `selfhosted` wins over the package
     * regardless of provider order. `saas` only binds when the package is
     * present, so a package-less checkout still runs (and the package-only tests
     * skip) instead of failing every test in the profile.
     */
    private function applyRegistrationProfile(): void
    {
        if (static::registrationProfile() === 'selfhosted') {
            $this->useSelfHostedRegistration();

            return;
        }

        if (static::registrationProfile() === 'saas' && class_exists(SaasRegistrationPolicyDefaults::class)) {
            $this->app->bind(RegistrationPolicyDefaults::class, SaasRegistrationPolicyDefaults::class);
            $this->app->bind(RegistrationGate::class, SaasRegistrationGate::class);
        }
    }
}
