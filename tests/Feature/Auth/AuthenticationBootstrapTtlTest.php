<?php

namespace Tests\Feature\Auth;

use App\Actions\Auth\CreateAuthenticationBootstrap;
use App\Contracts\AuthenticationBootstrapRepository;
use App\Enums\AuthenticationBootstrapPurpose;
use App\Models\AuthenticationBootstrap;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Covers the per-purpose lifetime of a single-use bootstrap.
 *
 * Enrolment lives hours (the visitor has to find the mail, install an
 * authenticator and come back); recovery lives minutes, because it can replace
 * the factor and a long-lived link is a long-lived takeover. Both are
 * deployment configuration. The assertions read `expires_at` directly, so no
 * test ever sleeps.
 */
class AuthenticationBootstrapTtlTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_enrolment_grant_lives_twelve_hours(): void
    {
        $user = User::factory()->create();

        $token = app(CreateAuthenticationBootstrap::class)->execute($user, AuthenticationBootstrapPurpose::TwoFactorEnrolment, notify: false);

        $grant = app(AuthenticationBootstrapRepository::class)
            ->findActive($token, AuthenticationBootstrapPurpose::TwoFactorEnrolment);

        $this->assertNotNull($grant);
        $this->assertSame(720, AuthenticationBootstrapPurpose::TwoFactorEnrolment->ttlMinutes());
        $this->assertEqualsWithDelta(
            now()->addHours(12)->getTimestamp(),
            $grant->expires_at->getTimestamp(),
            5,
        );
    }

    public function test_a_recovery_grant_lives_thirty_minutes(): void
    {
        $user = User::factory()->create();

        $token = app(CreateAuthenticationBootstrap::class)->execute($user, AuthenticationBootstrapPurpose::AccountRecovery, notify: false);

        $grant = app(AuthenticationBootstrapRepository::class)
            ->findActive($token, AuthenticationBootstrapPurpose::AccountRecovery);

        $this->assertNotNull($grant);
        $this->assertSame(30, AuthenticationBootstrapPurpose::AccountRecovery->ttlMinutes());
        $this->assertEqualsWithDelta(
            now()->addMinutes(30)->getTimestamp(),
            $grant->expires_at->getTimestamp(),
            5,
        );
    }

    /**
     * @return array<string, array{0: AuthenticationBootstrapPurpose, 1: int}>
     */
    public static function configuredTtls(): array
    {
        return [
            'enrolment' => [AuthenticationBootstrapPurpose::TwoFactorEnrolment, 45],
            'recovery' => [AuthenticationBootstrapPurpose::AccountRecovery, 5],
        ];
    }

    #[DataProvider('configuredTtls')]
    public function test_the_lifetime_of_each_purpose_is_configurable(
        AuthenticationBootstrapPurpose $purpose,
        int $minutes,
    ): void {
        config(['registration.bootstrap.ttl_minutes.'.$purpose->value => $minutes]);

        $user = User::factory()->create();
        $token = app(CreateAuthenticationBootstrap::class)->execute($user, $purpose, notify: false);

        $grant = app(AuthenticationBootstrapRepository::class)->findActive($token, $purpose);

        $this->assertNotNull($grant);
        $this->assertEqualsWithDelta(
            now()->addMinutes($minutes)->getTimestamp(),
            $grant->expires_at->getTimestamp(),
            5,
        );
    }

    public function test_a_nonsensical_configured_lifetime_falls_back_to_the_shipped_default(): void
    {
        config(['registration.bootstrap.ttl_minutes.two_factor_enrolment' => 0]);

        $user = User::factory()->create();
        $token = app(CreateAuthenticationBootstrap::class)->execute($user, AuthenticationBootstrapPurpose::TwoFactorEnrolment, notify: false);

        $grant = AuthenticationBootstrap::query()->whereNull('consumed_at')->firstOrFail();

        $this->assertEqualsWithDelta(
            now()->addMinutes(720)->getTimestamp(),
            $grant->expires_at->getTimestamp(),
            5,
        );
        $this->assertNotNull(
            app(AuthenticationBootstrapRepository::class)->findActive($token, AuthenticationBootstrapPurpose::TwoFactorEnrolment),
        );
    }
}
