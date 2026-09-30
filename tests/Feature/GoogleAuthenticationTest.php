<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\GoogleAccessRequested;
use App\Notifications\GoogleAccessResolved;
use App\Services\Auth\GoogleIdentityService;
use App\Services\Auth\GoogleIdTokenVerifier;
use Database\Seeders\RolesAndPermissionsSeeder;
use Google\Auth\AccessToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

class GoogleAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        Notification::fake();
    }

    public function test_new_google_account_waits_for_admin_approval_and_role(): void
    {
        Socialite::fake('google', SocialiteUser::fake([
            'id' => 'google-123',
            'email' => 'google@example.com',
            'email_verified' => true,
        ]));

        $admin = User::factory()->admin()->create();
        $this->get(route('google.callback'))->assertRedirect(route('login'));
        $user = User::query()->where('email', 'google@example.com')->firstOrFail();
        Notification::assertSentTo($admin, GoogleAccessRequested::class);
        $this->assertSame('pending', $user->approval_status);
        $this->assertFalse($user->roles()->exists());
        $this->assertGuest();

        $this->actingAs($admin)->post(route('admin.users.approve', $user), ['role' => 'user'])->assertRedirect();
        $user->refresh();
        $this->assertSame('approved', $user->approval_status);
        $this->assertTrue($user->hasRole('user'));
        Notification::assertSentTo($user, GoogleAccessResolved::class);
    }

    public function test_matching_email_requires_explicit_link(): void
    {
        $existing = User::factory()->user()->create(['email' => 'already@example.com']);
        Socialite::fake('google', SocialiteUser::fake([
            'id' => 'unlinked-google',
            'email' => $existing->email,
            'email_verified' => true,
        ]));

        $this->get(route('google.callback'))->assertRedirect(route('login'))->assertSessionHasErrors('google');
        $this->assertDatabaseMissing('google_identities', ['google_sub' => 'unlinked-google']);
        $this->assertSame(1, User::query()->where('email', $existing->email)->count());
    }

    public function test_rejected_user_cannot_get_mobile_token(): void
    {
        $user = User::factory()->create(['approval_status' => 'rejected']);
        $user->googleIdentity()->create(['google_sub' => 'rejected-sub']);
        $this->mock(GoogleIdTokenVerifier::class)->shouldReceive('verify')->once()->andReturn([
            'sub' => 'rejected-sub',
            'email' => $user->email,
            'name' => $user->name,
            'email_verified' => true,
        ]);

        $this->postJson(route('api.v1.auth.google.login'), ['id_token' => 'verified-id-token'])
            ->assertForbidden()->assertJsonPath('code', 'approval_rejected');
        $this->assertSame(0, $user->tokens()->count());
    }

    public function test_approved_google_user_gets_only_temporary_mobile_token_before_otp(): void
    {
        $user = User::factory()->withTwoFactor()->user()->create();
        $user->googleIdentity()->create(['google_sub' => 'approved-sub']);
        $this->mock(GoogleIdTokenVerifier::class)->shouldReceive('verify')->once()->andReturn([
            'sub' => 'approved-sub',
            'email' => $user->email,
            'name' => $user->name,
            'email_verified' => true,
        ]);

        $response = $this->postJson(route('api.v1.auth.google.login'), ['id_token' => 'verified-id-token'])
            ->assertOk()->assertJsonPath('requires_otp', true);
        $this->withToken($response->json('temp_token'))->getJson(route('api.v1.me'))->assertForbidden();
    }

    public function test_mobile_google_user_sets_up_otp_before_receiving_full_token(): void
    {
        $user = User::factory()->user()->create();
        $user->googleIdentity()->create(['google_sub' => 'first-login-sub']);
        $this->mock(GoogleIdTokenVerifier::class)->shouldReceive('verify')->once()->andReturn([
            'sub' => 'first-login-sub',
            'email' => $user->email,
            'name' => $user->name,
            'email_verified' => true,
        ]);
        $response = $this->postJson(route('api.v1.auth.google.login'), ['id_token' => 'verified-id-token'])
            ->assertOk()->assertJsonPath('requires_otp_setup', true);
        $temporary = $response->json('temp_token');
        $this->withToken($temporary)->getJson(route('api.v1.me'))->assertForbidden();
        $setup = $this->withToken($temporary)->postJson(route('api.v1.auth.google.otp.setup'))->assertOk();
        $secret = $setup->json('data.secret');
        $code = (new Google2FA)->getCurrentOtp($secret);
        $confirmed = $this->withToken($temporary)->postJson(route('api.v1.auth.google.otp.confirm'), [
            'challenge_id' => $setup->json('data.challenge_id'),
            'code' => $code,
        ])->assertOk();
        $this->assertTrue($user->fresh()->hasEnabledTwoFactorAuthentication());
        $this->app['auth']->forgetGuards();
        $this->withToken($confirmed->json('data.token'))->getJson(route('api.v1.me'))->assertOk();
    }

    public function test_mobile_verifier_rejects_invalid_signature_and_audience(): void
    {
        config()->set('services.google.client_id', 'expected-client');
        $accessToken = $this->mock(AccessToken::class);
        $accessToken->shouldReceive('verify')->twice()->andReturn(false, [
            'sub' => 'google-sub',
            'email' => 'user@example.com',
            'email_verified' => true,
            'iss' => 'https://accounts.google.com',
            'aud' => 'another-client',
        ]);
        $verifier = app(GoogleIdTokenVerifier::class);
        foreach (['expired-token', 'wrong-audience-token'] as $token) {
            try {
                $verifier->verify($token);
                $this->fail('Invalid Google ID token was accepted.');
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('id_token', $exception->errors());
            }
        }
    }

    public function test_approved_google_user_still_needs_web_otp(): void
    {
        $user = User::factory()->user()->create([
            'two_factor_secret' => encrypt('JBSWY3DPEHPK3PXP'),
            'two_factor_confirmed_at' => now(),
        ]);
        $user->googleIdentity()->create(['google_sub' => 'web-sub']);
        Socialite::fake('google', SocialiteUser::fake([
            'id' => 'web-sub',
            'email' => $user->email,
            'email_verified' => true,
        ]));

        $this->get(route('google.callback'))->assertRedirect(route('two-factor.login'));
        $this->assertGuest();
        $this->post(route('two-factor.login.store'), [
            'code' => (new Google2FA)->getCurrentOtp('JBSWY3DPEHPK3PXP'),
        ])->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($user);
    }

    public function test_pending_user_with_password_cannot_bypass_approval(): void
    {
        $user = User::factory()->withTwoFactor()->create(['approval_status' => 'pending']);
        $user->googleIdentity()->create(['google_sub' => 'pending-sub']);

        $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password'])
            ->assertSessionHasErrors('email');
        $this->postJson(route('api.v1.auth.login'), ['email' => $user->email, 'password' => 'password'])
            ->assertForbidden();
    }

    public function test_revoked_approval_blocks_existing_session_and_mobile_token(): void
    {
        $user = User::factory()->withTwoFactor()->user()->create();
        $user->googleIdentity()->create(['google_sub' => 'revoked-sub']);
        $token = $user->createToken('auth-token');

        $this->withToken($token->plainTextToken)->getJson(route('api.v1.me'))->assertOk();
        $user->update(['approval_status' => 'rejected']);
        $this->app['auth']->forgetGuards();
        $this->withToken($token->plainTextToken)->getJson(route('api.v1.me'))->assertForbidden();
        $this->actingAs($user)->get(route('dashboard'))->assertRedirect(route('login'));
    }

    public function test_existing_account_can_link_google_without_changing_approval_or_role(): void
    {
        $user = User::factory()->user()->create();
        app(GoogleIdentityService::class)->link($user, 'linked-sub', $user->email, true);

        $this->assertSame('linked-sub', $user->googleIdentity()->firstOrFail()->google_sub);
        $this->assertSame('approved', $user->fresh()->approval_status);
        $this->assertTrue($user->fresh()->hasRole('user'));
        $this->assertDatabaseCount('users', 1);
    }

    public function test_approved_google_user_sets_up_web_otp_before_access(): void
    {
        $user = User::factory()->user()->create();
        $user->googleIdentity()->create(['google_sub' => 'web-setup-sub']);
        Socialite::fake('google', SocialiteUser::fake([
            'id' => 'web-setup-sub',
            'email' => $user->email,
            'email_verified' => true,
        ]));

        $this->get(route('google.callback'))->assertRedirect(route('google.otp.setup'));
        $this->assertGuest();
        $this->get(route('google.otp.setup'))->assertOk();
        $secret = session('google.otp_secret');
        $this->post(route('google.otp.confirm'), [
            'code' => (new Google2FA)->getCurrentOtp($secret),
        ])->assertOk();
        $this->assertAuthenticatedAs($user);
        $this->assertTrue($user->fresh()->hasEnabledTwoFactorAuthentication());
    }
}
