<?php

namespace Tests\Feature\Api\V1;

use App\Models\Household;
use App\Models\HouseholdMember;
use App\Models\ListItem;
use App\Models\Permission;
use App\Models\PermissionGroup;
use App\Models\Product;
use App\Models\ShoppingList;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Covers the personal-product catalog of `GET /v1/personal-products`, which is
 * built from raw `list_items` / `shopping_lists` subqueries. Those subqueries
 * bypass Eloquent's soft-delete scope, so tombstones have to be filtered by hand.
 */
class PersonalProductVisibilityApiTest extends TestCase
{
    use RefreshDatabase;

    private User $member;

    private Household $household;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        // The route has no alias in ApiPermissionsMiddleware, so it is gated by
        // its own route name. The seeder does not ship that permission row.
        $group = PermissionGroup::create(['name' => 'Productos personales']);
        $permission = Permission::create([
            'name' => 'view personal products',
            'route_name' => 'personal-products.index',
            'description' => 'Ver productos personales',
            'permission_group_id' => $group->id,
        ]);

        $this->member = User::factory()->create();
        $this->member->assignRole('user');
        $this->member->roles()->firstOrFail()->givePermissionTo([$permission]);

        $this->household = Household::factory()->create(['created_by' => $this->member->id]);
        HouseholdMember::create([
            'user_id' => $this->member->id,
            'household_id' => $this->household->id,
            'role' => 'admin',
            'joined_at' => now(),
        ]);
        $this->member->update(['active_household_id' => $this->household->id]);

        Sanctum::actingAs($this->member);
    }

    public function test_a_personal_product_used_by_a_household_list_is_listed(): void
    {
        [, $product] = $this->householdItemWithPersonalProduct();

        $this->getJson(route('api.v1.personal-products.index'))
            ->assertOk()
            ->assertJsonFragment(['id' => $product->id]);
    }

    public function test_deleting_the_item_hides_its_personal_product_from_the_catalog(): void
    {
        [$item, $product] = $this->householdItemWithPersonalProduct();

        $this->getJson(route('api.v1.personal-products.index'))
            ->assertOk()
            ->assertJsonFragment(['id' => $product->id]);

        $this->deleteJson(
            route('api.v1.households.lists.items.destroy', [$this->household, $item->list, $item]),
            [],
            ['Idempotency-Key' => 'delete-item-with-personal-product'],
        )->assertNoContent();

        $this->getJson(route('api.v1.personal-products.index'))
            ->assertOk()
            ->assertJsonMissing(['id' => $product->id]);
    }

    public function test_deleting_the_list_hides_the_personal_product_of_its_items(): void
    {
        [$item, $product] = $this->householdItemWithPersonalProduct();

        $this->deleteJson(
            route('api.v1.households.lists.destroy', [$this->household, $item->list]),
            [],
            ['Idempotency-Key' => 'delete-list-with-personal-products'],
        )->assertNoContent();

        $this->getJson(route('api.v1.personal-products.index'))
            ->assertOk()
            ->assertJsonMissing(['id' => $product->id]);
    }

    /**
     * A household list holding a personal product owned by someone else. The
     * product must stay hidden from `$this->member` except through the item.
     *
     * @return array{ListItem, Product}
     */
    private function householdItemWithPersonalProduct(): array
    {
        $owner = User::factory()->create();
        $list = ShoppingList::factory()->create([
            'created_by' => $owner->id,
            'household_id' => $this->household->id,
        ]);
        $product = Product::factory()->create([
            'is_personal' => true,
            'created_by' => $owner->id,
        ]);
        $item = ListItem::factory()->create([
            'list_id' => $list->id,
            'product_id' => $product->id,
        ]);

        return [$item, $product];
    }
}
