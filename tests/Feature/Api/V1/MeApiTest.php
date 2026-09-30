<?php

namespace Tests\Feature\Api\V1;

use App\Models\Permission;
use App\Models\PermissionGroup;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MeApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_gets_profile_with_roles_and_permissions(): void
    {
        $group = PermissionGroup::create(['name' => 'Listas']);
        Permission::create(['name' => 'view lists', 'route_name' => 'households.lists.index', 'description' => 'Ver listas', 'permission_group_id' => $group->id]);
        Permission::create(['name' => 'store lists', 'route_name' => 'households.lists.store', 'description' => 'Crear listas', 'permission_group_id' => $group->id]);

        $role = Role::create(['name' => 'user', 'slug' => 'user']);
        $role->syncPermissions(['view lists', 'store lists']);

        $user = User::factory()->create();
        $user->assignRole($role);

        Sanctum::actingAs($user);

        $response = $this->getJson(route('api.v1.me'));

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'name',
                    'email',
                    'locale',
                    'email_verified_at',
                    'two_factor_enabled',
                    'active_household_id',
                    'roles',
                    'permissions',
                ],
            ])
            ->assertJsonPath('data.id', $user->id)
            ->assertJsonPath('data.email', $user->email);

        $this->assertSame(['user'], $response->json('data.roles'));
        $this->assertContains('v1.households.lists.index', $response->json('data.permissions'));
        $this->assertContains('v1.households.lists.store', $response->json('data.permissions'));
        $this->assertNotContains('households.lists.index', $response->json('data.permissions'));
    }

    public function test_user_without_roles_gets_account_level_operation_permissions(): void
    {
        $user = User::factory()->create();

        Sanctum::actingAs($user);

        $response = $this->getJson(route('api.v1.me'))
            ->assertOk()
            ->assertJsonPath('data.roles', []);

        $this->assertContains('v1.me', $response->json('data.permissions'));
    }

    public function test_permissions_are_deduplicated_across_roles(): void
    {
        $group = PermissionGroup::create(['name' => 'Listas']);
        Permission::create(['name' => 'view lists', 'route_name' => 'households.lists.index', 'description' => 'Ver listas', 'permission_group_id' => $group->id]);
        Permission::create(['name' => 'store lists', 'route_name' => 'households.lists.store', 'description' => 'Crear listas', 'permission_group_id' => $group->id]);

        $userRole = Role::create(['name' => 'user', 'slug' => 'user']);
        $userRole->syncPermissions(['view lists', 'store lists']);

        $moderatorRole = Role::create(['name' => 'Moderator', 'slug' => 'moderator']);
        $moderatorRole->syncPermissions(['view lists']);

        $user = User::factory()->create();
        $user->assignRole($userRole);
        $user->assignRole($moderatorRole);

        Sanctum::actingAs($user);

        $response = $this->getJson(route('api.v1.me'));

        $response->assertOk();
        $this->assertEqualsCanonicalizing(['user', 'moderator'], $response->json('data.roles'));
        $permissions = $response->json('data.permissions');
        $this->assertContains('v1.households.lists.index', $permissions);
        $this->assertSame(1, collect($permissions)->filter(fn (string $permission): bool => $permission === 'v1.households.lists.index')->count());
    }

    public function test_unauthenticated_user_cannot_access_profile(): void
    {
        $this->getJson(route('api.v1.me'))
            ->assertUnauthorized();
    }
}
