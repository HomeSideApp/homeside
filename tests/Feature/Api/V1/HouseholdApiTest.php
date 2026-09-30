<?php

namespace Tests\Feature\Api\V1;

use App\Enums\HouseholdRole;
use App\Models\Household;
use App\Models\HouseholdInvitation;
use App\Models\HouseholdMember;
use App\Models\Permission;
use App\Models\PermissionGroup;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class HouseholdApiTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Role $role;

    protected function setUp(): void
    {
        parent::setUp();

        $group = PermissionGroup::create(['name' => 'Hogares']);
        Permission::create(['name' => 'view households', 'route_name' => 'households.index', 'description' => 'Ver hogares', 'permission_group_id' => $group->id]);
        Permission::create(['name' => 'store households', 'route_name' => 'households.store', 'description' => 'Guardar hogar', 'permission_group_id' => $group->id]);
        Permission::create(['name' => 'view household', 'route_name' => 'households.show', 'description' => 'Ver un hogar', 'permission_group_id' => $group->id]);
        Permission::create(['name' => 'update households', 'route_name' => 'households.update', 'description' => 'Actualizar hogar', 'permission_group_id' => $group->id]);
        Permission::create(['name' => 'delete households', 'route_name' => 'households.destroy', 'description' => 'Eliminar hogar', 'permission_group_id' => $group->id]);
        Permission::create(['name' => 'invite members', 'route_name' => 'households.invite', 'description' => 'Invitar miembros', 'permission_group_id' => $group->id]);
        Permission::create(['name' => 'remove members', 'route_name' => 'households.remove', 'description' => 'Eliminar miembros', 'permission_group_id' => $group->id]);
        Permission::create(['name' => 'switch household', 'route_name' => 'households.switch', 'description' => 'Cambiar hogar activo', 'permission_group_id' => $group->id]);

        $this->role = Role::create(['name' => 'user', 'slug' => 'user']);
        $this->role->syncPermissions(Permission::all());

        Role::firstOrCreate(['slug' => 'user-invitation'], ['name' => 'User - Invitation', 'is_system' => true]);

        $this->user = User::factory()->create();
        $this->user->assignRole($this->role);

        Sanctum::actingAs($this->user);
    }

    public function test_user_can_create_household(): void
    {
        $response = $this->postJson(route('api.v1.households.store'), [
            'name' => 'Mi Hogar',
        ]);

        $response->assertCreated()
            ->assertJsonStructure(['data' => ['id', 'name', 'invite_code']]);

        $this->assertDatabaseHas('households', ['name' => 'Mi Hogar']);
        $this->assertDatabaseHas('household_members', [
            'user_id' => $this->user->id,
            'role' => HouseholdRole::Admin->value,
        ]);
    }

    public function test_household_creation_applies_modules_and_tags_like_web_endpoint(): void
    {
        $response = $this->postJson(route('api.v1.households.store'), [
            'name' => 'Hogar configurado',
            'modules' => [
                'shopping_lists' => true,
                'economy' => false,
            ],
            'tags' => ['Familiar'],
        ]);

        $response->assertCreated()
            ->assertJsonStructure(['data', 'warnings']);

        $householdId = $response->json('data.id');
        $this->assertDatabaseHas('household_modules', [
            'household_id' => $householdId,
            'module' => 'shopping_lists',
            'enabled' => true,
        ]);
        $this->assertDatabaseHas('household_modules', [
            'household_id' => $householdId,
            'module' => 'economy',
            'enabled' => false,
        ]);
        $this->assertDatabaseHas('tags', ['name' => 'Familiar']);
    }

    public function test_household_settings_route_matches_web_endpoint_shape(): void
    {
        $household = Household::factory()->create(['created_by' => $this->user->id]);
        HouseholdMember::factory()->create([
            'household_id' => $household->id,
            'user_id' => $this->user->id,
            'role' => HouseholdRole::Admin,
        ]);

        $this->putJson(route('api.v1.households.settings.update', $household), [
            'name' => 'Hogar actualizado',
            'description' => 'Configurado desde API',
        ])->assertOk()
            ->assertJsonPath('data.name', 'Hogar actualizado');

        $this->assertDatabaseHas('households', [
            'id' => $household->id,
            'description' => 'Configurado desde API',
        ]);
    }

    public function test_user_becomes_admin_on_creation(): void
    {
        $response = $this->postJson(route('api.v1.households.store'), [
            'name' => 'Test House',
        ]);

        $response->assertCreated();

        $household = Household::where('name', 'Test House')->first();
        $this->assertTrue($household->isAdmin($this->user));
    }

    public function test_user_can_list_own_households(): void
    {
        $household = Household::factory()->create([
            'created_by' => $this->user->id,
        ]);
        HouseholdMember::create([
            'household_id' => $household->id,
            'user_id' => $this->user->id,
            'role' => HouseholdRole::Admin,
            'joined_at' => now(),
        ]);

        $response = $this->getJson(route('api.v1.households.index'));

        $response->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_admin_can_update_household_settings(): void
    {
        $household = Household::factory()->create([
            'created_by' => $this->user->id,
        ]);
        HouseholdMember::create([
            'household_id' => $household->id,
            'user_id' => $this->user->id,
            'role' => HouseholdRole::Admin,
            'joined_at' => now(),
        ]);

        $response = $this->putJson(route('api.v1.households.update', $household), [
            'name' => 'Hogar actualizado',
            'description' => 'Descripción actualizada',
            'color' => '#123456',
            'tags' => ['Familiar'],
        ]);

        $response->assertOk()
            ->assertJsonPath('data.name', 'Hogar actualizado')
            ->assertJsonPath('data.description', 'Descripción actualizada')
            ->assertJsonPath('data.color', '#123456')
            ->assertJsonStructure(['data', 'warnings']);

        $this->assertDatabaseHas('households', [
            'id' => $household->id,
            'name' => 'Hogar actualizado',
            'description' => 'Descripción actualizada',
            'color' => '#123456',
        ]);
    }

    public function test_member_cannot_update_household_settings(): void
    {
        $household = Household::factory()->create();
        HouseholdMember::create([
            'household_id' => $household->id,
            'user_id' => $this->user->id,
            'role' => HouseholdRole::Member,
            'joined_at' => now(),
        ]);

        $this->putJson(route('api.v1.households.update', $household), [
            'name' => 'Nombre no permitido',
        ])->assertForbidden();
    }

    public function test_user_cannot_see_other_households(): void
    {
        $otherUser = User::factory()->create();
        $household = Household::factory()->create([
            'created_by' => $otherUser->id,
        ]);
        HouseholdMember::create([
            'household_id' => $household->id,
            'user_id' => $otherUser->id,
            'role' => HouseholdRole::Admin,
            'joined_at' => now(),
        ]);

        $response = $this->getJson(route('api.v1.households.index'));

        $response->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_admin_can_invite_member(): void
    {
        $household = Household::factory()->create([
            'created_by' => $this->user->id,
        ]);
        HouseholdMember::create([
            'household_id' => $household->id,
            'user_id' => $this->user->id,
            'role' => HouseholdRole::Admin,
            'joined_at' => now(),
        ]);

        $response = $this->postJson(route('api.v1.households.invite', $household), [
            'email' => 'newuser@example.com',
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('household_invitations', [
            'household_id' => $household->id,
            'email' => 'newuser@example.com',
        ]);
    }

    public function test_member_cannot_invite(): void
    {
        $household = Household::factory()->create([
            'created_by' => $this->user->id,
        ]);
        HouseholdMember::create([
            'household_id' => $household->id,
            'user_id' => $this->user->id,
            'role' => HouseholdRole::Member,
            'joined_at' => now(),
        ]);

        $response = $this->postJson(route('api.v1.households.invite', $household), [
            'email' => 'newuser@example.com',
        ]);

        $response->assertForbidden();
    }

    public function test_user_can_switch_active_household(): void
    {
        $household = Household::factory()->create([
            'created_by' => $this->user->id,
        ]);
        HouseholdMember::create([
            'household_id' => $household->id,
            'user_id' => $this->user->id,
            'role' => HouseholdRole::Admin,
            'joined_at' => now(),
        ]);

        $response = $this->postJson(route('api.v1.households.switch', $household));

        $response->assertOk();
        $this->assertDatabaseHas('users', [
            'id' => $this->user->id,
            'active_household_id' => $household->id,
        ]);
    }

    public function test_accept_invitation_adds_member(): void
    {
        $household = Household::factory()->create([
            'created_by' => $this->user->id,
        ]);

        $token = Str::random(64);

        HouseholdInvitation::create([
            'household_id' => $household->id,
            'invited_by' => $this->user->id,
            'email' => $this->user->email,
            'token' => $token,
            'expires_at' => now()->addDays(7),
        ]);

        $this->assertDatabaseHas('household_invitations', ['token' => $token]);

        $password = 'password123';

        $response = $this->postJson(route('api.v1.households.accept'), [
            'token' => $token,
            'password' => $password,
            'password_confirmation' => $password,
        ]);

        $response->assertOk()->assertJsonPath('data.status', 'accepted');
        $this->assertDatabaseHas('household_members', [
            'household_id' => $household->id,
            'user_id' => $this->user->id,
            'role' => HouseholdRole::Member->value,
        ]);
    }

    public function test_invitation_expires_after_7_days(): void
    {
        $household = Household::factory()->create([
            'created_by' => $this->user->id,
        ]);

        $token = Str::random(64);

        HouseholdInvitation::create([
            'household_id' => $household->id,
            'invited_by' => $this->user->id,
            'email' => $this->user->email,
            'token' => $token,
            'expires_at' => now()->subDay(),
        ]);

        $password = 'password123';

        $response = $this->postJson(route('api.v1.households.accept'), [
            'token' => $token,
            'password' => $password,
            'password_confirmation' => $password,
        ]);

        $response->assertConflict()->assertJsonPath('code', 'conflict');
    }

    public function test_cannot_invite_already_member(): void
    {
        $household = Household::factory()->create([
            'created_by' => $this->user->id,
        ]);
        HouseholdMember::create([
            'household_id' => $household->id,
            'user_id' => $this->user->id,
            'role' => HouseholdRole::Admin,
            'joined_at' => now(),
        ]);

        $response = $this->postJson(route('api.v1.households.invite', $household), [
            'email' => $this->user->email,
        ]);

        $response->assertUnprocessable();
    }

    public function test_cannot_remove_last_admin(): void
    {
        $household = Household::factory()->create([
            'created_by' => $this->user->id,
        ]);
        $member = HouseholdMember::create([
            'household_id' => $household->id,
            'user_id' => $this->user->id,
            'role' => HouseholdRole::Admin,
            'joined_at' => now(),
        ]);

        $response = $this->deleteJson(route('api.v1.households.remove', [$household, $member]));

        $response->assertUnprocessable();
    }

    public function test_returns_404_when_member_does_not_belong_to_route_household(): void
    {
        $household = Household::factory()->create(['created_by' => $this->user->id]);
        HouseholdMember::factory()->create([
            'household_id' => $household->id,
            'user_id' => $this->user->id,
            'role' => HouseholdRole::Admin,
        ]);
        $otherHousehold = Household::factory()->create();
        $foreignMember = HouseholdMember::factory()->create([
            'household_id' => $otherHousehold->id,
            'role' => HouseholdRole::Member,
        ]);

        $this->deleteJson(route('api.v1.households.remove', [$household, $foreignMember]))
            ->assertNotFound();

        $this->assertModelExists($foreignMember);
    }

    public function test_invite_code_only_visible_to_admin(): void
    {
        $household = Household::factory()->create([
            'created_by' => $this->user->id,
        ]);
        HouseholdMember::create([
            'household_id' => $household->id,
            'user_id' => $this->user->id,
            'role' => HouseholdRole::Admin,
            'joined_at' => now(),
        ]);

        $response = $this->getJson(route('api.v1.households.show', $household));

        $response->assertOk();
        $response->assertJsonFragment(['invite_code' => $household->invite_code]);
    }

    public function test_household_resource_uses_authorized_api_image_url(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('households/image.jpg', 'image-content');
        $household = Household::factory()->create([
            'created_by' => $this->user->id,
            'image_url' => 'households/image.jpg',
        ]);
        HouseholdMember::factory()->create([
            'household_id' => $household->id,
            'user_id' => $this->user->id,
            'role' => HouseholdRole::Admin,
        ]);

        $this->getJson(route('api.v1.households.show', $household))
            ->assertOk()
            ->assertJsonPath('data.image_url', route('api.v1.households.image', $household));

        $this->get(route('api.v1.households.image', $household))
            ->assertOk();
    }

    public function test_user_cannot_delete_household_they_dont_belong_to(): void
    {
        $otherUser = User::factory()->create();
        $household = Household::factory()->create([
            'created_by' => $otherUser->id,
        ]);

        $response = $this->deleteJson(route('api.v1.households.destroy', $household));

        $response->assertForbidden();
    }
}
