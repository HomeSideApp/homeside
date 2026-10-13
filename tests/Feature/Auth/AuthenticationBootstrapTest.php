<?php

namespace Tests\Feature\Auth;

use App\Actions\Auth\CompleteAuthenticationBootstrap;
use App\Actions\Auth\CreateAuthenticationBootstrap;
use App\Actions\Users\RevokeUserAccess;
use App\Actions\Users\SuspendUser;
use App\Contracts\AuthenticationBootstrapRepository;
use App\Enums\AuthenticationBootstrapPurpose;
use App\Http\Resources\Users\UserResource;
use App\Models\AuthenticationBootstrap;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia as Assert;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

/**
 * Covers the single-use authentication bootstrap that replaced the signed
 * `otp.setup` pair: single use, short TTL, user + purpose binding, atomic
 * consumption, no session on the GET, and a session only for an eligible
 * account after a verified TOTP code.
 */
class AuthenticationBootstrapTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_valid_token_shows_enrolment_without_creating_a_session(): void
    {
        $user = User::factory()->create();
        $token = $this->issue($user);

        $this->get($this->showUrl($token))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('auth/OtpSetup')
                ->where('confirmUrl', $this->confirmUrl($token))
            );

        $this->assertGuest();
        $this->get(route('dashboard'))->assertRedirect(route('login'));
    }

    public function test_an_already_enrolled_user_is_sent_to_login_without_a_session(): void
    {
        $user = User::factory()->withTwoFactor()->create();
        $token = $this->issue($user);

        $this->get($this->showUrl($token))
            ->assertRedirect(route('login'))
            ->assertSessionHas('status', __('app.errors.two_factor_already_enabled'));

        $this->assertGuest();
    }

    public function test_an_invalid_token_yields_a_clear_state_and_no_session(): void
    {
        $this->get($this->showUrl('not-a-real-token'))
            ->assertRedirect(route('login'))
            ->assertSessionHas('status', __('app.errors.bootstrap_invalid'));

        $this->assertGuest();
    }

    public function test_an_expired_token_fails(): void
    {
        $user = User::factory()->create();
        $token = app(AuthenticationBootstrapRepository::class)
            ->issue($user, AuthenticationBootstrapPurpose::TwoFactorEnrolment, -1);

        $this->get($this->showUrl($token))->assertRedirect(route('login'));
        $this->assertNull(app(AuthenticationBootstrapRepository::class)->findActive($token, AuthenticationBootstrapPurpose::TwoFactorEnrolment));
        $this->assertGuest();
    }

    public function test_a_consumed_token_fails(): void
    {
        $user = User::factory()->create();
        $token = $this->issue($user);

        $this->assertNotNull(app(AuthenticationBootstrapRepository::class)->consume($token, AuthenticationBootstrapPurpose::TwoFactorEnrolment));

        $this->get($this->showUrl($token))->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_consuming_a_token_twice_fails_the_replay(): void
    {
        $user = User::factory()->create();
        $token = $this->issue($user);
        $secret = (new Google2FA)->generateSecretKey();
        $bootstrap = $this->active($token);
        $code = (new Google2FA)->getCurrentOtp($secret);

        $this->withSession([$this->secretKey($bootstrap) => $secret])
            ->post($this->confirmUrl($token), ['code' => $code])
            ->assertOk();

        $this->assertAuthenticatedAs($user->fresh());

        $this->post(route('logout'));
        $this->assertGuest();

        $this->withSession([$this->secretKey($bootstrap) => $secret])
            ->post($this->confirmUrl($token), ['code' => $code])
            ->assertRedirect(route('login'));

        $this->assertGuest();
    }

    public function test_a_wrong_code_does_not_consume_the_bootstrap_and_a_retry_succeeds(): void
    {
        $user = User::factory()->create();
        $token = $this->issue($user);
        $secret = (new Google2FA)->generateSecretKey();
        $bootstrap = $this->active($token);
        $valid = (new Google2FA)->getCurrentOtp($secret);
        $invalid = str_pad((string) (((int) $valid + 500000) % 1000000), 6, '0', STR_PAD_LEFT);

        $this->withSession([$this->secretKey($bootstrap) => $secret])
            ->post($this->confirmUrl($token), ['code' => $invalid])
            ->assertSessionHasErrors('code');

        $this->assertNull($user->fresh()->two_factor_confirmed_at);
        $this->assertNotNull($this->active($token));

        $this->withSession([$this->secretKey($bootstrap) => $secret])
            ->post($this->confirmUrl($token), ['code' => $valid])
            ->assertOk();

        $this->assertNotNull($user->fresh()->two_factor_confirmed_at);
    }

    public function test_a_token_can_only_enrol_the_user_it_was_issued_for(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $token = $this->issue($owner);
        $secret = (new Google2FA)->generateSecretKey();
        $code = (new Google2FA)->getCurrentOtp($secret);

        $consumed = app(CompleteAuthenticationBootstrap::class)
            ->execute($token, AuthenticationBootstrapPurpose::TwoFactorEnrolment, $secret, $code);

        $this->assertNotNull($consumed);
        $this->assertTrue($consumed->user->is($owner));
        $this->assertNotNull($owner->fresh()->two_factor_confirmed_at);
        $this->assertNull($other->fresh()->two_factor_confirmed_at);
    }

    public function test_issuing_a_new_bootstrap_invalidates_the_previous_one(): void
    {
        $user = User::factory()->create();

        $first = $this->issue($user);
        $second = $this->issue($user);

        $this->assertNull($this->active($first));
        $this->assertNotNull($this->active($second));
    }

    public function test_a_pending_user_completes_enrolment_but_gets_no_session(): void
    {
        $user = User::factory()->create(['approval_status' => 'pending']);
        $token = $this->issue($user);
        $secret = (new Google2FA)->generateSecretKey();
        $bootstrap = $this->active($token);
        $code = (new Google2FA)->getCurrentOtp($secret);

        $this->get($this->showUrl($token))->assertOk();

        $this->withSession([$this->secretKey($bootstrap) => $secret])
            ->post($this->confirmUrl($token), ['code' => $code])
            ->assertRedirect(route('login'));

        $this->assertGuest();
        $this->assertNotNull($user->fresh()->two_factor_confirmed_at);
        $this->get(route('dashboard'))->assertRedirect(route('login'));
    }

    public function test_rejected_suspended_and_blocked_users_cannot_turn_enrolment_into_a_session(): void
    {
        $accounts = [
            User::factory()->create(['approval_status' => 'rejected']),
            User::factory()->suspended()->create(),
            User::factory()->blocked()->create(),
        ];

        foreach ($accounts as $user) {
            $token = $this->issue($user);
            $secret = (new Google2FA)->generateSecretKey();
            $bootstrap = $this->active($token);
            $code = (new Google2FA)->getCurrentOtp($secret);

            $this->withSession([$this->secretKey($bootstrap) => $secret])
                ->post($this->confirmUrl($token), ['code' => $code])
                ->assertRedirect(route('login'));

            $this->assertGuest();
            $this->get(route('dashboard'))->assertRedirect(route('login'));
        }
    }

    public function test_a_recovery_bootstrap_can_never_be_redeemed_as_an_enrolment_bootstrap(): void
    {
        $user = User::factory()->create();
        $secret = (new Google2FA)->generateSecretKey();
        $code = (new Google2FA)->getCurrentOtp($secret);

        $recovery = $this->issue($user, AuthenticationBootstrapPurpose::AccountRecovery);

        $this->assertNull(
            app(CompleteAuthenticationBootstrap::class)
                ->execute($recovery, AuthenticationBootstrapPurpose::TwoFactorEnrolment, $secret, $code)
        );
        $this->assertNull($user->fresh()->two_factor_confirmed_at);

        $enrolment = $this->issue($user, AuthenticationBootstrapPurpose::TwoFactorEnrolment);

        $this->assertNull(
            app(AuthenticationBootstrapRepository::class)->consume($enrolment, AuthenticationBootstrapPurpose::AccountRecovery)
        );
    }

    public function test_a_recovery_bootstrap_cannot_grant_access_to_a_non_approved_account(): void
    {
        $accounts = [
            User::factory()->withTwoFactor()->create(['approval_status' => 'pending']),
            User::factory()->withTwoFactor()->create(['approval_status' => 'rejected']),
            User::factory()->withTwoFactor()->suspended()->create(),
            User::factory()->withTwoFactor()->blocked()->create(),
        ];

        foreach ($accounts as $user) {
            $token = $this->issue($user, AuthenticationBootstrapPurpose::AccountRecovery);

            $this->get(route('otp.reset', ['token' => $token]))->assertForbidden();

            $this->post(
                route('otp.reset.confirm', ['token' => $token]),
                ['code' => '123456'],
            )->assertForbidden();

            $this->assertGuest();
        }
    }

    public function test_revoking_access_invalidates_a_link_issued_before_the_suspension(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $target = User::factory()->create();
        $token = $this->issue($target);

        app(SuspendUser::class)->execute($target, $admin);

        $this->assertNull($this->active($token));
        $this->get($this->showUrl($token))->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_revoke_user_access_deletes_outstanding_bootstraps(): void
    {
        $user = User::factory()->create();
        $enrolment = $this->issue($user, AuthenticationBootstrapPurpose::TwoFactorEnrolment);
        $recovery = $this->issue($user, AuthenticationBootstrapPurpose::AccountRecovery);

        app(RevokeUserAccess::class)->execute($user);

        $this->assertSame(0, AuthenticationBootstrap::query()->count());
        $this->assertNull($this->active($enrolment, AuthenticationBootstrapPurpose::TwoFactorEnrolment));
        $this->assertNull($this->active($recovery, AuthenticationBootstrapPurpose::AccountRecovery));
    }

    public function test_the_raw_token_and_the_secret_never_leak_outside_the_enrolment_step(): void
    {
        $user = User::factory()->create();
        $token = $this->issue($user);
        $secret = (new Google2FA)->generateSecretKey();
        $bootstrap = $this->active($token);

        // Only a keyed digest is persisted; the raw token never touches the DB.
        $rows = json_encode(DB::table('authentication_bootstraps')->get(), JSON_THROW_ON_ERROR);
        $this->assertStringNotContainsString($token, $rows);
        $this->assertNotSame($token, $bootstrap->getRawOriginal('token_hash'));

        // The API resource never exposes the secret.
        $resource = (new UserResource($user))->resolve();
        $this->assertArrayNotHasKey('two_factor_secret', $resource);
        $this->assertArrayNotHasKey('token_hash', $resource);
        $this->assertStringNotContainsString($secret, json_encode($resource, JSON_THROW_ON_ERROR));

        // The append-only audit table has nowhere to put a token or a secret.
        $auditColumns = Schema::getColumnListing('registration_audits');
        $this->assertNotContains('token_hash', $auditColumns);
        $this->assertNotContains('two_factor_secret', $auditColumns);
    }

    /**
     * Issue an enrolment (or recovery) token for the user.
     */
    private function issue(User $user, AuthenticationBootstrapPurpose $purpose = AuthenticationBootstrapPurpose::TwoFactorEnrolment): string
    {
        return app(CreateAuthenticationBootstrap::class)->execute($user, $purpose);
    }

    /**
     * Resolve the active grant for a token.
     */
    private function active(
        string $token,
        AuthenticationBootstrapPurpose $purpose = AuthenticationBootstrapPurpose::TwoFactorEnrolment,
    ): ?AuthenticationBootstrap {
        return app(AuthenticationBootstrapRepository::class)->findActive($token, $purpose);
    }

    private function showUrl(string $token): string
    {
        return route('auth.bootstrap.two-factor.show', ['token' => $token]);
    }

    private function confirmUrl(string $token): string
    {
        return route('auth.bootstrap.two-factor.confirm', ['token' => $token]);
    }

    private function secretKey(AuthenticationBootstrap $bootstrap): string
    {
        return 'auth_bootstrap_secret_'.$bootstrap->id;
    }
}
