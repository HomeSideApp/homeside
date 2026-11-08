<?php

namespace Tests\Feature\Registration;

use App\Contracts\AuthenticationBootstrapRepository;
use App\Enums\AuthenticationBootstrapPurpose;
use App\Enums\RegistrationChannel;
use App\Enums\RegistrationPolicy;
use App\Models\AuthenticationBootstrap;
use App\Models\User;
use App\Services\Auth\RegistrationSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

/**
 * Proves the web registration loop is closed.
 *
 * Web login always demands a second factor and a freshly registered account has
 * none, so a registration that ends on `/login` is a dead end. The open channel
 * must hand the visitor a single-use enrolment bootstrap instead; an
 * approval-required registration must NOT receive a usable enrolment path.
 */
class WebRegistrationBootstrapTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_open_web_registration_enrols_two_factor_and_lands_on_the_dashboard(): void
    {
        app(RegistrationSettings::class)->setPolicy(RegistrationChannel::Web, RegistrationPolicy::Open);

        $response = $this->post(route('register.store'), [
            'name' => 'Web Bootstrap',
            'email' => 'web-bootstrap@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertRedirect();
        $location = (string) $response->headers->get('Location');
        $this->assertStringContainsString('auth/bootstrap', $location);

        $user = User::where('email', 'web-bootstrap@example.com')->firstOrFail();

        // Registering never signs the visitor in, and opening the enrolment
        // page is not authentication either.
        $this->assertGuest();
        $this->get($location)->assertOk();
        $this->assertGuest();

        // Email verification is satisfied before the single-use step is used.
        $user->markEmailAsVerified();

        $token = $this->tokenFrom($location);
        $bootstrap = app(AuthenticationBootstrapRepository::class)
            ->findActive($token, AuthenticationBootstrapPurpose::TwoFactorEnrolment);
        $this->assertNotNull($bootstrap);

        $secret = (new Google2FA)->generateSecretKey();

        $this->withSession(['auth_bootstrap_secret_'.$bootstrap->id => $secret])
            ->post(route('auth.bootstrap.two-factor.confirm', ['token' => $token]), [
                'code' => (new Google2FA)->getCurrentOtp($secret),
            ])
            ->assertOk();

        $user->refresh();
        $this->assertNotNull($user->two_factor_confirmed_at);
        $this->assertAuthenticatedAs($user);
        $this->get(route('dashboard'))->assertOk();
    }

    public function test_an_approval_required_web_registration_gets_no_usable_enrolment_path(): void
    {
        app(RegistrationSettings::class)->setPolicy(RegistrationChannel::Web, RegistrationPolicy::ApprovalRequired);

        $this->post(route('register.store'), [
            'name' => 'Web Pending',
            'email' => 'web-pending-bootstrap@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertRedirect(route('login'))
            ->assertSessionHas('status', __('app.auth.registration_pending'));

        $user = User::where('email', 'web-pending-bootstrap@example.com')->firstOrFail();

        // No grant was minted for the pending account, so there is no token to
        // open and no session that could reach the dashboard.
        $this->assertSame(0, AuthenticationBootstrap::query()->where('user_id', $user->id)->count());
        $this->assertGuest();
        $this->get(route('dashboard'))->assertRedirect(route('login'));
    }

    /**
     * Extract the plaintext token from a bootstrap enrolment URL.
     *
     * @param  string  $url  The `/auth/bootstrap/{token}/2fa` URL.
     * @return string The plaintext token.
     */
    private function tokenFrom(string $url): string
    {
        $segments = array_values(array_filter(explode('/', (string) parse_url($url, PHP_URL_PATH))));

        return $segments[2] ?? '';
    }
}
