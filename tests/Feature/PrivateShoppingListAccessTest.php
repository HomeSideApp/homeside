<?php

namespace Tests\Feature;

use App\Http\Middleware\HandleInertiaRequests;
use App\Models\Household;
use App\Models\HouseholdMember;
use App\Models\Permission;
use App\Models\Product;
use App\Models\Role;
use App\Models\ShoppingList;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class PrivateShoppingListAccessTest extends TestCase
{
    use RefreshDatabase;

    #[TestWith(['admin'])]
    #[TestWith(['user'])]
    public function test_user_without_household_can_create_private_lists_in_web_and_api(string $role): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $user = User::factory()->create(['active_household_id' => null]);
        $user->assignRole($role);

        $this->actingAs($user)->get(route('lists.index'))->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('lists/Index')
                ->has('households', 0)->where('activeHouseholdId', null)
                ->where('can.createPersonalList', true));
        $this->get(route('lists.create'))->assertOk();

        $this->post(route('lists.store'), ['name' => 'Private web list'])->assertRedirect();
        $this->postJson(route('api.v1.lists.store'), ['name' => 'Private API list'])->assertCreated();

        foreach (['Private web list', 'Private API list'] as $name) {
            $this->assertDatabaseHas('shopping_lists', ['name' => $name, 'created_by' => $user->id, 'household_id' => null]);
        }
        $list = ShoppingList::query()->where('created_by', $user->id)->where('name', 'Private web list')->firstOrFail();
        $this->get(route('lists.show', $list))->assertOk();
        $this->assertSame(0, $user->householdMemberships()->count());
    }

    public function test_user_without_creation_permission_receives_false_capability_and_cannot_create(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $role = Role::create(['name' => 'View lists', 'slug' => 'private-list-viewer']);
        $role->permissions()->sync([Permission::query()->where('route_name', 'households.lists.index')->firstOrFail()->id]);
        $user = User::factory()->create();
        $user->assignRole($role);

        $this->actingAs($user)->get(route('lists.index'))->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('can.createPersonalList', false));
        $this->post(route('lists.store'), ['name' => 'Forbidden'])->assertForbidden();
        $this->assertDatabaseMissing('shopping_lists', ['name' => 'Forbidden']);
    }

    public function test_platform_admin_without_household_cannot_read_someone_elses_private_list(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $admin = User::factory()->admin()->create(['active_household_id' => null]);
        $list = ShoppingList::factory()->create(['household_id' => null, 'created_by' => User::factory()]);

        $this->actingAs($admin)->get(route('lists.show', $list))->assertForbidden();
        $this->getJson(route('api.v1.lists.show', $list))->assertForbidden();
    }

    #[TestWith(['admin'])]
    #[TestWith(['user'])]
    public function test_user_without_household_can_search_products_in_own_private_list(string $role): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $user = User::factory()->create(['active_household_id' => null]);
        $user->assignRole($role);
        $list = ShoppingList::factory()->create(['created_by' => $user->id, 'household_id' => null]);
        $product = Product::factory()->create([
            'name' => 'Searchable private product', 'created_by' => $user->id, 'is_personal' => true,
        ]);
        Product::factory()->create(['name' => 'Searchable foreign product', 'is_personal' => true]);

        $this->actingAs($user)->get(route('lists.search-products', [$list, 'q' => 'Searchable']), [
            'X-Inertia' => 'true',
            'X-Inertia-Version' => app(HandleInertiaRequests::class)->version(request()),
            'X-Inertia-Partial-Component' => 'lists/Show',
            'X-Inertia-Partial-Data' => 'searchResults',
        ])->assertOk()
            ->assertJsonPath('component', 'lists/Show')
            ->assertJsonCount(1, 'props.searchResults')
            ->assertJsonPath('props.searchResults.0.id', $product->id)
            ->assertJsonPath('props.searchResults.0.name', 'Searchable private product');
    }

    public function test_user_without_household_can_quick_create_and_add_product_to_own_private_list(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $user = User::factory()->admin()->create(['active_household_id' => null]);
        $list = ShoppingList::factory()->create(['created_by' => $user->id, 'household_id' => null]);

        $this->actingAs($user)->post(route('lists.search-products.quick-create', $list), [
            'name' => 'New private list product',
        ])->assertRedirect(route('lists.show', $list));

        $product = Product::query()->where('name', 'New private list product')->firstOrFail();
        $this->assertSame($user->id, $product->created_by);
        $this->assertDatabaseHas('list_items', [
            'list_id' => $list->id, 'product_id' => $product->id, 'quantity' => 1,
        ]);
    }

    public function test_platform_admin_cannot_search_or_add_products_in_someone_elses_private_list(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $user = User::factory()->admin()->create();
        $list = ShoppingList::factory()->create(['household_id' => null]);

        $this->actingAs($user)->get(route('lists.search-products', [$list, 'q' => 'Product']))->assertForbidden();
        $this->post(route('lists.search-products.quick-create', $list), ['name' => 'Forbidden product'])->assertForbidden();

        $this->assertDatabaseMissing('products', ['name' => 'Forbidden product']);
        $this->assertDatabaseMissing('list_items', ['list_id' => $list->id]);
    }

    public function test_private_product_routes_reject_household_lists(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $user = User::factory()->admin()->create();
        $household = Household::factory()->create(['created_by' => $user->id]);
        HouseholdMember::factory()->create(['household_id' => $household->id, 'user_id' => $user->id]);
        $list = ShoppingList::factory()->create(['created_by' => $user->id, 'household_id' => $household->id]);

        $this->actingAs($user)->get(route('lists.search-products', [$list, 'q' => 'Product']))->assertNotFound();
        $this->post(route('lists.search-products.quick-create', $list), ['name' => 'Wrong scope product'])->assertNotFound();

        $this->assertDatabaseMissing('products', ['name' => 'Wrong scope product']);
        $this->assertDatabaseMissing('list_items', ['list_id' => $list->id]);
    }
}
