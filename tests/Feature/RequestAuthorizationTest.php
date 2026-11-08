<?php

namespace Tests\Feature;

use App\Actions\Auth\CreateAuthenticationBootstrap;
use App\Enums\AuthenticationBootstrapPurpose;
use App\Enums\HouseholdRole;
use App\Models\AuthenticationBootstrap;
use App\Models\Contact;
use App\Models\Household;
use App\Models\HouseholdMember;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use HomeSide\AiAgents\Models\AiProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class RequestAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    #[TestWith(['settings.ai-providers.update'])]
    #[TestWith(['api.v1.me.ai-providers.update'])]
    public function test_foreign_personal_provider_is_hidden_before_invalid_fields_are_validated(string $routeName): void
    {
        $user = User::factory()->create();
        $provider = AiProvider::factory()->create(['household_id' => null, 'user_id' => User::factory()]);
        $name = $provider->name;

        $this->actingAs($user)->putJson(route($routeName, $provider), ['name' => []])->assertNotFound();

        $this->assertSame($name, $provider->refresh()->name);
    }

    #[TestWith(['households.ai-providers.update'])]
    #[TestWith(['api.v1.households.ai-providers.update'])]
    public function test_household_provider_rejects_another_route_household_before_validating_fields(string $routeName): void
    {
        $user = User::factory()->create();
        $household = Household::factory()->create(['created_by' => $user->id]);
        HouseholdMember::factory()->create(['household_id' => $household->id, 'user_id' => $user->id, 'role' => HouseholdRole::Admin]);
        $other = Household::factory()->create(['created_by' => $user->id]);
        HouseholdMember::factory()->create(['household_id' => $other->id, 'user_id' => $user->id, 'role' => HouseholdRole::Admin]);
        $provider = AiProvider::factory()->create(['household_id' => $other->id, 'user_id' => null]);
        $name = $provider->name;

        $this->actingAs($user)->putJson(route($routeName, [$household, $provider]), ['name' => []])->assertNotFound();

        $this->assertSame($name, $provider->refresh()->name);
    }

    #[TestWith(['contacts.update'])]
    #[TestWith(['api.v1.contacts.update'])]
    public function test_foreign_contact_is_forbidden_before_invalid_fields_are_validated(string $routeName): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $user = User::factory()->create();
        $user->assignRole('user');
        $contact = Contact::create(['user_id' => User::factory()->create()->id, 'type' => 'person', 'display_name' => 'Private']);
        $name = $contact->display_name;

        $this->actingAs($user)->putJson(route($routeName, $contact), ['display_name' => []])->assertForbidden();

        $this->assertSame($name, $contact->refresh()->display_name);
    }

    #[TestWith([['roles' => []]])]
    #[TestWith([['can_create_households_override' => 'invalid']])]
    public function test_user_data_editor_cannot_submit_administrative_fields_even_when_invalid(array $fields): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $role = Role::create(['name' => 'Data editor', 'slug' => 'request-data-editor']);
        $role->syncPermissions([Permission::query()->where('route_name', 'admin.users.update')->firstOrFail()]);
        $actor = User::factory()->create();
        $actor->assignRole($role);
        $target = User::factory()->create(['name' => 'Original']);

        $this->actingAs($actor)->patchJson(route('api.v1.admin.users.update', $target), ['name' => 'Changed', ...$fields])->assertForbidden();

        $this->assertSame('Original', $target->refresh()->name);
        $this->assertFalse($target->hasRole('admin'));
    }

    public function test_single_use_recovery_bootstrap_validates_code_before_replacing_existing_secret(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $user = User::factory()->create([
            'two_factor_secret' => encrypt('existing-secret'),
            'two_factor_confirmed_at' => now(),
        ]);
        $user->assignRole('user');
        // Recovery moved off signed URLs onto purpose-bound single-use bootstraps.
        $token = app(CreateAuthenticationBootstrap::class)
            ->execute($user, AuthenticationBootstrapPurpose::AccountRecovery);
        $bootstrap = AuthenticationBootstrap::query()->where('purpose', 'account_recovery')->firstOrFail();

        $this->withSession(['otp_reset_secret_'.$bootstrap->id => 'pending-secret'])
            ->postJson(route('otp.reset.confirm', ['token' => $token]), ['code' => []])
            ->assertUnprocessable()->assertJsonValidationErrors('code');

        $this->assertSame('existing-secret', decrypt($user->refresh()->two_factor_secret));
    }

    #[TestWith(['en-US', 'The challenge is invalid or expired.'])]
    #[TestWith(['es-ES', 'El desafío no es válido o ha caducado.'])]
    public function test_expired_two_factor_challenge_uses_the_user_locale(string $locale, string $message): void
    {
        $user = User::factory()->create(['locale' => $locale]);

        $this->actingAs($user)->postJson(route('api.v1.me.two-factor.confirm'), [
            'challenge_id' => fake()->uuid(),
            'code' => '123456',
        ])->assertUnprocessable()->assertJsonPath('message', $message);

        $this->assertNull($user->refresh()->two_factor_secret);
    }

    public function test_login_recovery_code_is_validated_and_consumed_before_session_authentication(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $user = User::factory()->create([
            'two_factor_secret' => encrypt('existing-secret'),
            'two_factor_confirmed_at' => now(),
            'two_factor_recovery_codes' => encrypt(json_encode(['recover-once', 'remaining'], JSON_THROW_ON_ERROR)),
        ]);
        $user->assignRole('user');

        $this->withSession(['login.id' => $user->id])
            ->post(route('two-factor.login.store'), ['recovery_code' => 'recover-once'])
            ->assertRedirect('/dashboard');

        $this->assertAuthenticatedAs($user);
        $this->assertSame(['remaining'], json_decode(decrypt($user->refresh()->two_factor_recovery_codes), true));
    }

    #[TestWith(['en-US', 'api.v1.admin.roles.update', 409, 'System roles cannot be modified.'])]
    #[TestWith(['es-ES', 'api.v1.admin.roles.update', 409, 'No se puede modificar un rol del sistema.'])]
    #[TestWith(['en-US', 'admin.roles.update', 403, 'System roles cannot be modified.'])]
    #[TestWith(['es-ES', 'admin.roles.update', 403, 'No se puede modificar un rol del sistema.'])]
    public function test_system_role_rejection_is_localized_and_precedes_invalid_fields(string $locale, string $routeName, int $status, string $message): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $actor = User::factory()->create(['locale' => $locale]);
        $actor->assignRole('admin');
        $role = Role::query()->where('slug', 'user')->firstOrFail();
        $name = $role->name;

        $this->actingAs($actor)->json($routeName === 'admin.roles.update' ? 'PUT' : 'PATCH', route($routeName, $role), ['name' => []])
            ->assertStatus($status)->assertJsonPath('message', $message);

        $this->assertSame($name, $role->refresh()->name);
    }
}
