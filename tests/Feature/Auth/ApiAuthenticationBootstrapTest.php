<?php

namespace Tests\Feature\Auth;

use App\Actions\Auth\CreateAuthenticationBootstrap;
use App\Contracts\AuthenticationBootstrapRepository;
use App\Enums\AuthenticationBootstrapPurpose;
use App\Enums\RegistrationChannel;
use App\Enums\RegistrationPolicy;
use App\Models\User;
use App\Services\Auth\RegistrationSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

/**
 * Covers the API adapter of the single-use enrolment bootstrap: the register
 * response hands out the bootstrap token, the bootstrap-authenticated setup
 * exchange returns the SECRET in the body, and the confirm returns the auth
 * token only for an account allowed to hold a session.
 */
class ApiAuthenticationBootstrapTest extends TestCase
{
    use RefreshDatabase;

    public function test_register_returns_a_bootstrap_token_but_never_the_secret(): void
    {
        app(RegistrationSettings::class)->setPolicy(RegistrationChannel::Api, RegistrationPolicy::Open);

        $response = $this->postJson(route('api.v1.auth.register'), [
            'name' => 'API User',
            'email' => 'api-bootstrap@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertCreated();

        $response->assertJsonPath('state', 'verify_email');
        $this->assertIsString($response->json('enrolment.token'));

        // The enrolment secret only ever comes from the setup exchange.
        $body = (string) $response->getContent();
        $this->assertStringNotContainsString('"secret"', $body);
        $this->assertStringNotContainsString('two_factor_secret', $body);
    }

    public function test_a_pending_registration_gets_an_enrolment_grant_without_a_token(): void
    {
        app(RegistrationSettings::class)->setPolicy(RegistrationChannel::Api, RegistrationPolicy::ApprovalRequired);

        $response = $this->postJson(route('api.v1.auth.register'), [
            'name' => 'Pending User',
            'email' => 'api-pending-bootstrap@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertCreated();

        $response->assertJsonPath('state', 'approval_pending');
        $this->assertIsString($response->json('enrolment.token'));
        $this->assertNull($response->json('token'));
        $this->assertNull($response->json('data.token'));
    }

    public function test_an_invalid_bootstrap_token_cannot_start_an_enrolment_exchange(): void
    {
        $this->postJson(route('api.v1.auth.two-factor.setup'), ['enrolment_token' => 'not-a-token'])
            ->assertStatus(401)
            ->assertJsonPath('code', 'enrolment_token_invalid');
    }

    public function test_a_recovery_bootstrap_token_cannot_start_an_enrolment_exchange(): void
    {
        $user = User::factory()->create();
        $token = app(CreateAuthenticationBootstrap::class)->execute($user, AuthenticationBootstrapPurpose::AccountRecovery);

        $this->postJson(route('api.v1.auth.two-factor.setup'), ['enrolment_token' => $token])
            ->assertStatus(401);
    }

    public function test_the_setup_exchange_delivers_the_secret_only_to_the_bootstrap_holder(): void
    {
        $user = User::factory()->create();
        $token = $this->issue($user);

        $response = $this->postJson(route('api.v1.auth.two-factor.setup'), ['enrolment_token' => $token])
            ->assertCreated();

        $this->assertIsString($response->json('data.secret'));
        $this->assertIsString($response->json('data.challenge_id'));
        $this->assertIsString($response->json('data.provisioning_uri'));

        // The raw token is never echoed back.
        $this->assertStringNotContainsString($token, (string) $response->getContent());
    }

    public function test_a_wrong_code_does_not_consume_the_grant_and_a_retry_succeeds(): void
    {
        $user = User::factory()->create();
        $token = $this->issue($user);
        [$challengeId, $secret] = $this->startChallenge($token);
        $valid = (new Google2FA)->getCurrentOtp($secret);
        $invalid = str_pad((string) (((int) $valid + 500000) % 1000000), 6, '0', STR_PAD_LEFT);

        $this->postJson(route('api.v1.auth.two-factor.setup.confirm'), [
            'enrolment_token' => $token,
            'challenge_id' => $challengeId,
            'code' => $invalid,
        ])->assertStatus(422)->assertJsonPath('code', 'otp_code_invalid');

        $this->assertNull($user->fresh()->two_factor_confirmed_at);
        $this->assertNotNull(
            app(AuthenticationBootstrapRepository::class)->findActive($token, AuthenticationBootstrapPurpose::TwoFactorEnrolment)
        );

        $response = $this->postJson(route('api.v1.auth.two-factor.setup.confirm'), [
            'enrolment_token' => $token,
            'challenge_id' => $challengeId,
            'code' => $valid,
        ])->assertOk();

        $this->assertNotNull($user->fresh()->two_factor_confirmed_at);
        $this->assertIsString($response->json('token'));
        $this->assertStringNotContainsString($secret, (string) $response->getContent());
        $this->assertSame(1, $user->tokens()->count());
    }

    public function test_a_consumed_bootstrap_cannot_be_replayed(): void
    {
        $user = User::factory()->create();
        $token = $this->issue($user);
        [$challengeId, $secret] = $this->startChallenge($token);
        $code = (new Google2FA)->getCurrentOtp($secret);

        $this->postJson(route('api.v1.auth.two-factor.setup.confirm'), [
            'enrolment_token' => $token,
            'challenge_id' => $challengeId,
            'code' => $code,
        ])->assertOk();

        $this->postJson(route('api.v1.auth.two-factor.setup.confirm'), [
            'enrolment_token' => $token,
            'challenge_id' => $challengeId,
            'code' => $code,
        ])->assertStatus(422);

        $this->assertSame(1, $user->tokens()->count());
    }

    public function test_an_unverified_account_completes_enrolment_without_a_token(): void
    {
        $user = User::factory()->unverified()->create();
        $token = $this->issue($user);
        [$challengeId, $secret] = $this->startChallenge($token);

        $response = $this->postJson(route('api.v1.auth.two-factor.setup.confirm'), [
            'enrolment_token' => $token,
            'challenge_id' => $challengeId,
            'code' => (new Google2FA)->getCurrentOtp($secret),
        ])->assertStatus(202);

        $response->assertJsonPath('state', 'verify_email');
        $this->assertNull($response->json('token'));
        $this->assertNotNull($user->fresh()->two_factor_confirmed_at);
        $this->assertSame(0, $user->tokens()->count());
    }

    public function test_a_pending_account_completes_enrolment_without_a_token(): void
    {
        $user = User::factory()->create(['approval_status' => 'pending']);
        $token = $this->issue($user);
        [$challengeId, $secret] = $this->startChallenge($token);

        $response = $this->postJson(route('api.v1.auth.two-factor.setup.confirm'), [
            'enrolment_token' => $token,
            'challenge_id' => $challengeId,
            'code' => (new Google2FA)->getCurrentOtp($secret),
        ])->assertStatus(202);

        $response->assertJsonPath('state', 'approval_pending');
        $this->assertNull($response->json('token'));
        $this->assertSame(0, $user->tokens()->count());
    }

    /**
     * Issue an enrolment grant for the user.
     */
    private function issue(User $user): string
    {
        return app(CreateAuthenticationBootstrap::class)->execute($user, AuthenticationBootstrapPurpose::TwoFactorEnrolment);
    }

    /**
     * Start the setup exchange and return the challenge id and secret.
     *
     * @return array{0: string, 1: string}
     */
    private function startChallenge(string $token): array
    {
        $response = $this->postJson(route('api.v1.auth.two-factor.setup'), ['enrolment_token' => $token])
            ->assertCreated();

        return [(string) $response->json('data.challenge_id'), (string) $response->json('data.secret')];
    }
}
