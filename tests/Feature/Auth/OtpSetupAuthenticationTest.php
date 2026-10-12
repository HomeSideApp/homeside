<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

/**
 * Proves that the signed OTP setup link is onboarding, never by itself an
 * authentication mechanism: opening it creates no session, and completing
 * enrolment authenticates only when every access requirement is met.
 */
class OtpSetupAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_opening_the_setup_link_with_two_factor_configured_does_not_create_a_session(): void
    {
        $user = User::factory()->withTwoFactor()->create();

        $response = $this->get(URL::temporarySignedRoute('otp.setup', now()->addMinutes(30), ['user' => $user->id]));

        $response->assertRedirect(route('login'));
        $this->assertGuest();

        $this->get(route('dashboard'))->assertRedirect(route('login'));
    }

    public function test_opening_the_setup_link_without_two_factor_renders_enrolment_without_a_session(): void
    {
        $user = User::factory()->create();

        $response = $this->get(URL::temporarySignedRoute('otp.setup', now()->addMinutes(30), ['user' => $user->id]));

        $response->assertOk();
        $this->assertGuest();
    }

    public function test_completing_enrolment_authenticates_an_eligible_user(): void
    {
        $user = User::factory()->create();
        $secret = (new Google2FA)->generateSecretKey();
        $code = (new Google2FA)->getCurrentOtp($secret);

        $response = $this->withSession(["otp_secret_{$user->id}" => $secret])
            ->post(URL::temporarySignedRoute('otp.confirm', now()->addMinutes(30), ['user' => $user->id]), [
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
        $secret = (new Google2FA)->generateSecretKey();
        $code = (new Google2FA)->getCurrentOtp($secret);

        $response = $this->withSession(["otp_secret_{$user->id}" => $secret])
            ->post(URL::temporarySignedRoute('otp.confirm', now()->addMinutes(30), ['user' => $user->id]), [
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
        $secret = (new Google2FA)->generateSecretKey();
        $code = (new Google2FA)->getCurrentOtp($secret);

        $setup = URL::temporarySignedRoute('otp.setup', now()->addMinutes(30), ['user' => $user->id]);
        $confirm = URL::temporarySignedRoute('otp.confirm', now()->addMinutes(30), ['user' => $user->id]);

        $this->get($setup)->assertForbidden();

        $this->withSession(["otp_secret_{$user->id}" => $secret])
            ->post($confirm, ['code' => $code])
            ->assertForbidden();

        $this->assertGuest();
    }
}
