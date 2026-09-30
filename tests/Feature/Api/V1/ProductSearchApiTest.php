<?php

namespace Tests\Feature\Api\V1;

use App\Models\Permission;
use App\Models\PermissionGroup;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProductSearchApiTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $listasGroup = PermissionGroup::create(['name' => 'Listas']);
        Permission::create(['name' => 'view lists', 'route_name' => 'lists.index', 'description' => 'Ver listas', 'permission_group_id' => $listasGroup->id]);

        $productosGroup = PermissionGroup::create(['name' => 'Productos']);
        Permission::create(['name' => 'quick create products', 'route_name' => 'households.products.quick-create', 'description' => 'Creación rápida de productos', 'permission_group_id' => $productosGroup->id]);

        $role = Role::create(['name' => 'user', 'slug' => 'user']);
        $role->syncPermissions(['view lists', 'quick create products']);

        $this->user = User::factory()->create();
        $this->user->assignRole($role);

        Sanctum::actingAs($this->user);
    }

    public function test_user_can_search_products(): void
    {
        Product::create(['name' => 'Leche', 'slug' => 'leche', 'created_by' => $this->user->id]);
        Product::create(['name' => 'Pan', 'slug' => 'pan', 'created_by' => $this->user->id]);

        $response = $this->getJson(route('api.v1.products.search', ['q' => 'Leche']));

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Leche');
    }

    public function test_search_returns_422_for_empty_query(): void
    {
        Product::create(['name' => 'Leche', 'slug' => 'leche', 'created_by' => $this->user->id]);

        $response = $this->getJson(route('api.v1.products.search', ['q' => '']));

        $response->assertUnprocessable()
            ->assertJsonValidationErrors('q')
            ->assertJsonPath('code', 'validation_failed');
    }

    public function test_user_can_get_recent_products(): void
    {
        $response = $this->getJson(route('api.v1.products.recent'));

        $response->assertOk()
            ->assertJsonStructure(['data']);
    }

    public function test_user_can_quick_create_product(): void
    {
        $response = $this->postJson(route('api.v1.products.quick-create'), [
            'name' => 'Producto Rápido',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.name', 'Producto Rápido');

        $this->assertDatabaseHas('products', [
            'name' => 'Producto Rápido',
            'created_by' => $this->user->id,
        ]);
    }

    public function test_quick_create_validates_name_required(): void
    {
        $response = $this->postJson(route('api.v1.products.quick-create'), [
            'name' => '',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors('name');
    }
}
