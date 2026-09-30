<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Household;
use App\Models\HouseholdMember;
use App\Models\Permission;
use App\Models\PermissionGroup;
use App\Models\Product;
use App\Models\ProductUsage;
use App\Models\Role;
use App\Models\ShoppingList;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductUsageTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Household $household;

    protected ShoppingList $list;

    protected Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();

        $listasGroup = PermissionGroup::create(['name' => 'Listas']);
        Permission::create(['name' => 'store list items', 'route_name' => 'households.lists.items.store', 'description' => 'Añadir items', 'permission_group_id' => $listasGroup->id]);

        $role = Role::create(['name' => 'user', 'slug' => 'user']);
        $role->syncPermissions(['store list items']);
        $this->user->assignRole($role);

        $this->household = Household::factory()->create(['created_by' => $this->user->id]);
        HouseholdMember::create([
            'user_id' => $this->user->id,
            'household_id' => $this->household->id,
            'role' => 'admin',
            'joined_at' => now(),
        ]);
        $this->user->update(['active_household_id' => $this->household->id]);

        $this->list = ShoppingList::factory()->create([
            'created_by' => $this->user->id,
            'household_id' => $this->household->id,
        ]);
        $category = Category::create(['name' => 'Frutas', 'slug' => 'frutas', 'color' => '#26A69A']);
        $this->product = Product::create(['name' => 'Manzana', 'slug' => 'manzana', 'category_id' => $category->id, 'created_by' => $this->user->id]);
    }

    public function test_recent_products_returns_empty_when_no_usage(): void
    {
        $response = $this->actingAs($this->user)
            ->getJson(route('api.products.recent'));

        $response->assertOk()
            ->assertJsonCount(0);
    }

    public function test_recent_products_returns_used_products(): void
    {
        ProductUsage::create([
            'user_id' => $this->user->id,
            'product_id' => $this->product->id,
            'last_used_at' => now(),
            'usage_count' => 1,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson(route('api.products.recent'));

        $response->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.name', 'Manzana');
    }

    public function test_recent_products_ordered_by_last_used(): void
    {
        $product2 = Product::create(['name' => 'Naranja', 'slug' => 'naranja', 'created_by' => $this->user->id]);

        ProductUsage::create(['user_id' => $this->user->id, 'product_id' => $this->product->id, 'last_used_at' => now()->subHour(), 'usage_count' => 1]);
        ProductUsage::create(['user_id' => $this->user->id, 'product_id' => $product2->id, 'last_used_at' => now(), 'usage_count' => 1]);

        $response = $this->actingAs($this->user)
            ->getJson(route('api.products.recent'));

        $response->assertOk()
            ->assertJsonPath('0.name', 'Naranja')
            ->assertJsonPath('1.name', 'Manzana');
    }

    public function test_recent_products_only_shows_own_usage(): void
    {
        $otherUser = User::factory()->create();

        ProductUsage::create(['user_id' => $this->user->id, 'product_id' => $this->product->id, 'last_used_at' => now(), 'usage_count' => 1]);
        ProductUsage::create(['user_id' => $otherUser->id, 'product_id' => $this->product->id, 'last_used_at' => now(), 'usage_count' => 1]);

        $response = $this->actingAs($this->user)
            ->getJson(route('api.products.recent'));

        $response->assertOk()
            ->assertJsonCount(1);
    }

    public function test_adding_product_to_list_tracks_usage(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson(route('households.lists.items.store', [$this->household, $this->list]), [
                'product_id' => $this->product->id,
                'quantity' => 1,
            ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('product_usage', [
            'user_id' => $this->user->id,
            'product_id' => $this->product->id,
        ]);
    }

    public function test_usage_count_increments_on_repeated_add(): void
    {
        ProductUsage::create([
            'user_id' => $this->user->id,
            'product_id' => $this->product->id,
            'list_id' => $this->list->id,
            'last_used_at' => now()->subDay(),
            'usage_count' => 1,
        ]);

        $this->actingAs($this->user)
            ->postJson(route('households.lists.items.store', [$this->household, $this->list]), [
                'product_id' => $this->product->id,
                'quantity' => 1,
            ]);

        $usage = ProductUsage::where('user_id', $this->user->id)
            ->where('product_id', $this->product->id)
            ->where('list_id', $this->list->id)
            ->first();
        $this->assertEquals(2, $usage->usage_count);
    }
}
