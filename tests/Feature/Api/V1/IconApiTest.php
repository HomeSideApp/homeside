<?php

namespace Tests\Feature\Api\V1;

use App\Models\Permission;
use App\Models\PermissionGroup;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class IconApiTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $group = PermissionGroup::create(['name' => 'Listas']);
        Permission::create(['name' => 'view lists', 'route_name' => 'lists.index', 'description' => 'Ver listas', 'permission_group_id' => $group->id]);

        $role = Role::create(['name' => 'user', 'slug' => 'user']);
        $role->syncPermissions(['view lists']);

        $this->user = User::factory()->create();
        $this->user->assignRole($role);

        Sanctum::actingAs($this->user);
    }

    public function test_user_can_list_icons(): void
    {
        $response = $this->getJson(route('api.v1.icons.index'));

        $response
            ->assertOk()
            ->assertJsonFragment([
                'name' => 'avocado.webp',
                'url' => asset('icons/webp/avocado.webp'),
                'type' => 'static',
            ]);

        $this->assertSame(
            collect($response->json())->pluck('name')->sort()->values()->all(),
            collect($response->json())->pluck('name')->all(),
        );
    }

    public function test_unauthenticated_user_cannot_list_icons(): void
    {
        $this->app['auth']->forgetGuards();

        $response = $this->getJson(route('api.v1.icons.index'));

        $response->assertUnauthorized();
    }
}
