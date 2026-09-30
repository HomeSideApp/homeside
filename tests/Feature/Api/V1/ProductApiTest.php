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

class ProductApiTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $group = PermissionGroup::create(['name' => 'Productos']);
        Permission::create(['name' => 'view products', 'route_name' => 'products.index', 'description' => 'Ver productos', 'permission_group_id' => $group->id]);
        Permission::create(['name' => 'store products', 'route_name' => 'households.products.store', 'description' => 'Crear productos', 'permission_group_id' => $group->id]);
        Permission::create(['name' => 'update products', 'route_name' => 'admin.products.update', 'description' => 'Actualizar productos', 'permission_group_id' => $group->id]);
        Permission::create(['name' => 'delete products', 'route_name' => 'admin.products.destroy', 'description' => 'Eliminar productos', 'permission_group_id' => $group->id]);

        $role = Role::create(['name' => 'user', 'slug' => 'user']);
        $role->syncPermissions(['view products', 'store products', 'update products', 'delete products']);

        $this->user = User::factory()->create();
        $this->user->assignRole($role);

        Sanctum::actingAs($this->user);
    }

    public function test_user_can_list_products(): void
    {
        Product::create(['name' => 'Leche', 'slug' => 'leche', 'created_by' => $this->user->id]);
        Product::create(['name' => 'Pan', 'slug' => 'pan', 'created_by' => $this->user->id]);

        $response = $this->getJson(route('api.v1.products.index'));

        $response->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'name', 'slug', 'icon', 'created_at', 'updated_at'],
                ],
            ]);
    }

    public function test_user_can_search_products_via_api(): void
    {
        Product::create(['name' => 'Leche', 'slug' => 'leche', 'created_by' => $this->user->id]);
        Product::create(['name' => 'Pan', 'slug' => 'pan', 'created_by' => $this->user->id]);

        $response = $this->getJson(route('api.v1.products.search', ['q' => 'Leche']));

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Leche');
    }

    public function test_user_can_create_product(): void
    {
        $response = $this->postJson(route('api.v1.products.store'), [
            'name' => 'Leche',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.name', 'Leche');

        $this->assertDatabaseHas('products', [
            'name' => 'Leche',
            'created_by' => $this->user->id,
        ]);
    }

    public function test_validation_error_returns_422(): void
    {
        $response = $this->postJson(route('api.v1.products.store'), [
            'name' => '',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors('name');
    }

    public function test_user_with_admin_web_permission_can_update_product_through_api(): void
    {
        $product = Product::create(['name' => 'Leche', 'slug' => 'leche', 'created_by' => $this->user->id]);

        $this->putJson(route('api.v1.products.update', $product), [
            'name' => 'Leche entera',
        ])->assertOk()
            ->assertJsonPath('data.name', 'Leche entera');

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'slug' => 'leche-entera',
        ]);
    }

    public function test_user_can_delete_product(): void
    {
        $product = Product::create(['name' => 'Leche', 'slug' => 'leche', 'created_by' => $this->user->id]);

        $response = $this->deleteJson(route('api.v1.products.destroy', $product));

        $response->assertNoContent();
        $this->assertDatabaseMissing('products', ['id' => $product->id]);
    }

    public function test_unauthenticated_user_cannot_list_products(): void
    {
        $this->app['auth']->forgetGuards();

        $response = $this->getJson(route('api.v1.products.index'));

        $response->assertUnauthorized();
    }

    public function test_dates_are_formatted_as_iso_8601(): void
    {
        Product::create(['name' => 'Leche', 'slug' => 'leche', 'created_by' => $this->user->id]);

        $response = $this->getJson(route('api.v1.products.index'));

        $response->assertOk();
        $date = $response->json('data.0.created_at');
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}/', $date);
    }
}
