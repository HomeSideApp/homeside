<?php

namespace Tests\Feature\Auth;

use App\Actions\Auth\CreateAuthenticationBootstrap;
use App\Contracts\AuthenticationBootstrapRepository;
use App\Enums\AuthenticationBootstrapPurpose;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

/**
 * Proves that the single-use enrolment bootstrap is onboarding, never by itself
 * an authentication mechanism: opening it creates no session, and completing
 * enrolment authenticates only when every access requirement is met.
 *
 * This is the successor of the signed `otp.setup` coverage (Phase 1): the route
 * and its request pair were retired in Phase 7 in favour of the bootstrap
 * transport, but the security property tested here is unchanged.
 */
class OtpSetupAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_opening_the_enrolment_token_with_two_factor_configured_does_not_create_a_session(): void
    {
        $user = User::factory()->withTwoFactor()->create();
        $token = $this->issue($user);

        $response = $this->get(route('auth.bootstrap.two-factor.show', ['token' => $token]));

        $response->assertRedirect(route('login'));
        $this->assertGuest();

        $this->get(route('dashboard'))->assertRedirect(route('login'));
    }

    public function test_opening_the_enrolment_token_without_two_factor_renders_enrolment_without_a_session(): void
    {
        $user = User::factory()->create();
        $token = $this->issue($user);

        $response = $this->get(route('auth.bootstrap.two-factor.show', ['token' => $token]));

        $response->assertOk();
        $this->assertGuest();
    }

    public function test_completing_enrolment_authenticates_an_eligible_user(): void
    {
        $user = User::factory()->create();
        $token = $this->issue($user);
        $secret = (new Google2FA)->generateSecretKey();
        $code = (new Google2FA)->getCurrentOtp($secret);
        $bootstrap = app(AuthenticationBootstrapRepository::class)->findActive($token, AuthenticationBootstrapPurpose::TwoFactorEnrolment);
        $this->assertNotNull($bootstrap);

        $response = $this->withSession(["auth_bootstrap_secret_{$bootstrap->id}" => $secret])
            ->post(route('auth.bootstrap.two-factor.confirm', ['token' => $token]), [
                'code' => $code,
            ]);

        $response->assertOk();
        $this->assertNotNull($user->fresh()->two_factor_confirmed_at);

        // An approved account with a verified email finishes onboarding signed in,
        // which is what the target flow requires (`confirm TOTP -> dashboard`).
        $this->assertAuthenticatedAs($user->fresh());
        $this->get(route('dashboard'))->assertOk();
    }

    public function test_completing_enrolment_does_not_authenticate_an_unverified_email(): void
    {
        $user = User::factory()->unverified()->create();
        $token = $this->issue($user);
        $secret = (new Google2FA)->generateSecretKey();
        $code = (new Google2FA)->getCurrentOtp($secret);
        $bootstrap = app(AuthenticationBootstrapRepository::class)->findActive($token, AuthenticationBootstrapPurpose::TwoFactorEnrolment);
        $this->assertNotNull($bootstrap);

        $response = $this->withSession(["auth_bootstrap_secret_{$bootstrap->id}" => $secret])
            ->post(route('auth.bootstrap.two-factor.confirm', ['token' => $token]), [
                'code' => $code,
            ]);

        $response->assertRedirect(route('login'));
        $this->assertGuest();

        // Enrolment still completed — only the session was withheld.
        $this->assertNotNull($user->fresh()->two_factor_confirmed_at);
        $this->get(route('dashboard'))->assertRedirect(route('login'));
    }

    public function test_completing_enrolment_cannot_bypass_pending_approval(): void
    {
        $user = User::factory()->create(['approval_status' => 'pending']);
        $token = $this->issue($user);
        $secret = (new Google2FA)->generateSecretKey();
        $code = (new Google2FA)->getCurrentOtp($secret);
        $bootstrap = app(AuthenticationBootstrapRepository::class)->findActive($token, AuthenticationBootstrapPurpose::TwoFactorEnrolment);
        $this->assertNotNull($bootstrap);

        // The enrolment page is reachable (see the dedicated bootstrap test),
        // but completing it never produces a session for a pending account.
        $this->get(route('auth.bootstrap.two-factor.show', ['token' => $token]))->assertOk();

        $this->withSession(["auth_bootstrap_secret_{$bootstrap->id}" => $secret])
            ->post(route('auth.bootstrap.two-factor.confirm', ['token' => $token]), ['code' => $code])
            ->assertRedirect(route('login'));

        $this->assertGuest();
        $this->assertNotNull($user->fresh()->two_factor_confirmed_at);
        $this->get(route('dashboard'))->assertRedirect(route('login'));
    }

    /**
     * Issue an enrolment grant for the user.
     */
    private function issue(User $user): string
    {
        return app(CreateAuthenticationBootstrap::class)->execute($user, AuthenticationBootstrapPurpose::TwoFactorEnrolment);
    }
}
