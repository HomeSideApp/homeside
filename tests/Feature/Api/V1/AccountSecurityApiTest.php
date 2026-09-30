<?php

namespace Tests\Feature\Api\V1;

use App\Models\Household;
use App\Models\HouseholdMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AccountSecurityApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_update_returns_the_expanded_user_contract(): void
    {
        $user = User::factory()->create(['password' => 'password']);
        Sanctum::actingAs($user);

        $this->patchJson(route('api.v1.me.profile.update'), [
            'name' => 'Ada', 'email' => 'ada@example.com', 'locale' => 'es-ES',
        ])->assertOk()
            ->assertJsonPath('data.name', 'Ada')
            ->assertJsonPath('data.email_verified_at', null)
            ->assertJsonPath('data.two_factor_enabled', false)
            ->assertJsonPath('data.households_enabled', true)
            ->assertJsonStructure(['data' => ['active_household_id', 'households_enabled', 'roles', 'permissions']]);

        $this->assertDatabaseHas('users', ['id' => $user->id, 'email' => 'ada@example.com', 'locale' => 'es-ES']);
    }

    public function test_account_delete_requires_and_consumes_security_confirmation(): void
    {
        $user = User::factory()->create(['password' => 'password']);
        Sanctum::actingAs($user);

        $this->deleteJson(route('api.v1.me.destroy'))->assertForbidden();
        $confirmation = $this->postJson(route('api.v1.me.security-confirmations.store'), ['password' => 'password'])
            ->assertCreated()
            ->json('data.token');

        $this->deleteJson(route('api.v1.me.destroy'), [], ['X-Security-Confirmation' => $confirmation])->assertNoContent();
        $this->assertDatabaseMissing('users', ['id' => $user->id]);
    }

    public function test_profile_update_can_disable_households_and_clear_the_active_context(): void
    {
        $user = User::factory()->create();
        $household = Household::factory()->create(['created_by' => $user->id]);
        HouseholdMember::factory()->create([
            'user_id' => $user->id,
            'household_id' => $household->id,
        ]);
        $user->update(['active_household_id' => $household->id]);
        Sanctum::actingAs($user);

        $this->patchJson(route('api.v1.me.profile.update'), [
            'name' => $user->name,
            'email' => $user->email,
            'locale' => $user->locale,
            'households_enabled' => false,
        ])->assertOk()
            ->assertJsonPath('data.households_enabled', false)
            ->assertJsonPath('data.active_household_id', null);

        $this->assertTrue($user->fresh()->isMemberOf($household));
    }

    public function test_forgot_password_is_neutral_for_unknown_accounts(): void
    {
        $this->postJson(route('api.v1.auth.password.forgot'), ['email' => 'unknown@example.com'])
            ->assertStatus(202)
            ->assertJsonPath('message', 'If the account exists, recovery instructions have been sent.');
    }

    public function test_two_factor_setup_returns_a_local_qr_provisioning_contract(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson(route('api.v1.me.two-factor.setup'))
            ->assertCreated()
            ->assertJsonStructure(['data' => ['challenge_id', 'secret', 'provisioning_uri', 'expires_at']])
            ->assertJsonMissing(['qr_code_url']);
    }

    public function test_public_recovery_rate_limit_uses_the_common_error_envelope(): void
    {
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->postJson(route('api.v1.auth.password.forgot'), ['email' => 'rate-limit@example.com'])
                ->assertAccepted();
        }

        $this->postJson(route('api.v1.auth.password.forgot'), ['email' => 'rate-limit@example.com'])
            ->assertTooManyRequests()
            ->assertHeader('Retry-After')
            ->assertJsonPath('code', 'rate_limited');
    }
}
