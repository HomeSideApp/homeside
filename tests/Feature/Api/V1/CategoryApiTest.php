<?php

namespace Tests\Feature\Api\V1;

use App\Models\Category;
use App\Models\Permission;
use App\Models\PermissionGroup;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CategoryApiTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $group = PermissionGroup::create(['name' => 'Listas']);
        Permission::create(['name' => 'view lists', 'route_name' => 'lists.index', 'description' => 'Ver listas', 'permission_group_id' => $group->id]);
        Permission::create(['name' => 'store categories', 'route_name' => 'admin.categories.store', 'description' => 'Crear categorías', 'permission_group_id' => $group->id]);
        Permission::create(['name' => 'update categories', 'route_name' => 'admin.categories.update', 'description' => 'Actualizar categorías', 'permission_group_id' => $group->id]);
        Permission::create(['name' => 'delete categories', 'route_name' => 'admin.categories.destroy', 'description' => 'Eliminar categorías', 'permission_group_id' => $group->id]);

        $role = Role::create(['name' => 'user', 'slug' => 'user']);
        $role->syncPermissions(['view lists', 'store categories', 'update categories', 'delete categories']);

        $this->user = User::factory()->create();
        $this->user->assignRole($role);

        Sanctum::actingAs($this->user);
    }

    public function test_user_can_list_categories(): void
    {
        Category::create(['name' => 'Frutas', 'slug' => 'frutas', 'sort_order' => 1]);
        Category::create(['name' => 'Verduras', 'slug' => 'verduras', 'sort_order' => 2]);
        Category::create(['name' => 'Lácteos', 'slug' => 'lacteos', 'sort_order' => 3]);

        $response = $this->getJson(route('api.v1.categories.index'));

        $response->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'name', 'slug', 'icon', 'color'],
                ],
            ]);
    }

    public function test_categories_are_ordered_by_sort_order(): void
    {
        Category::create(['name' => 'Z', 'slug' => 'z', 'sort_order' => 2]);
        Category::create(['name' => 'A', 'slug' => 'a', 'sort_order' => 1]);

        $response = $this->getJson(route('api.v1.categories.index'));

        $response->assertOk();
        $this->assertEquals('A', $response->json('data.0.name'));
        $this->assertEquals('Z', $response->json('data.1.name'));
    }

    public function test_user_with_web_permission_can_create_category_through_api(): void
    {
        $this->postJson(route('api.v1.categories.store'), [
            'name' => 'Congelados',
            'color' => '#2563EB',
        ])->assertCreated()
            ->assertJsonPath('data.name', 'Congelados');

        $this->assertDatabaseHas('categories', [
            'name' => 'Congelados',
            'slug' => 'congelados',
        ]);
    }

    public function test_user_with_admin_web_permissions_can_update_and_delete_category_through_api(): void
    {
        $category = Category::create(['name' => 'Frutas', 'slug' => 'frutas', 'sort_order' => 1]);

        $this->putJson(route('api.v1.categories.update', $category), [
            'name' => 'Fruta fresca',
            'color' => '#16A34A',
        ])->assertOk()
            ->assertJsonPath('data.name', 'Fruta fresca');

        $this->deleteJson(route('api.v1.categories.destroy', $category))
            ->assertNoContent();

        $this->assertModelMissing($category);
    }

    public function test_unauthenticated_user_cannot_list_categories(): void
    {
        $this->app['auth']->forgetGuards();

        $response = $this->getJson(route('api.v1.categories.index'));

        $response->assertUnauthorized();
    }
}
