<?php

namespace Tests\Feature\Api\V1\Auth;

use App\Models\Permission;
use App\Models\PermissionGroup;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ApiLogoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_logout(): void
    {
        $group = PermissionGroup::create(['name' => 'Listas']);
        Permission::create(['name' => 'view lists', 'route_name' => 'lists.index', 'description' => 'Ver listas', 'permission_group_id' => $group->id]);

        $role = Role::create(['name' => 'user', 'slug' => 'user']);
        $role->syncPermissions(['view lists']);

        $user = User::factory()->create();
        $user->assignRole($role);

        Sanctum::actingAs($user);

        $response = $this->postJson(route('api.v1.auth.logout'));

        $response->assertNoContent();
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_unauthenticated_user_cannot_logout(): void
    {
        $response = $this->postJson(route('api.v1.auth.logout'));

        $response->assertUnauthorized();
    }
}
