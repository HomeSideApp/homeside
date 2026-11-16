<?php

namespace Tests\Feature;

use App\Models\Household;
use App\Models\HouseholdMember;
use App\Models\ListItem;
use App\Models\Product;
use App\Models\RecipeIngredient;
use App\Models\ShoppingList;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Covers personal products whose creator shares them: creation from shopping
 * lists, the grouped admin list and the promotion of a whole name group.
 */
class AdminPersonalProductTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_sharing_creator_quick_creates_a_personal_product_from_a_household_list(): void
    {
        [$user, $household, $list] = $this->householdContext(shares: true);

        $this->actingAs($user)
            ->post(route('households.lists.search-products.quick-create', [$household, $list]), ['name' => 'Cerveza'])
            ->assertRedirect(route('households.lists.show', [$household, $list]));

        $product = Product::query()->where('name', 'Cerveza')->sole();

        $this->assertTrue($product->is_personal);
        $this->assertSame($user->id, $product->created_by);
        $this->assertDatabaseHas('list_items', [
            'list_id' => $list->id,
            'product_id' => $product->id,
        ]);

        $this->actingAs($this->admin())
            ->get(route('admin.personal-products'))
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('admin/PersonalProducts/Index')
                ->has('products.data', 1)
                ->where('products.data.0.id', $product->id)
                ->where('products.data.0.name', 'Cerveza')
                ->where('products.data.0.users', 1));
    }

    public function test_creator_without_sharing_still_quick_creates_a_global_product(): void
    {
        [$user, $household, $list] = $this->householdContext(shares: false);

        $this->actingAs($user)
            ->post(route('households.lists.search-products.quick-create', [$household, $list]), ['name' => 'Cerveza'])
            ->assertRedirect();

        $product = Product::query()->where('name', 'Cerveza')->sole();

        $this->assertFalse($product->is_personal);
        $this->assertSame($user->id, $product->created_by);

        $this->actingAs($this->admin())
            ->get(route('admin.personal-products'))
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('admin/PersonalProducts/Index')
                ->has('products.data', 0)
                ->where('products.total', 0));
    }

    public function test_sharing_creator_reuses_their_own_personal_product_instead_of_duplicating_it(): void
    {
        [$user, $household, $list] = $this->householdContext(shares: true);
        $otherList = ShoppingList::factory()->create([
            'household_id' => $household->id,
            'created_by' => $user->id,
        ]);

        foreach ([$list, $otherList] as $target) {
            $this->actingAs($user)
                ->post(route('households.lists.search-products.quick-create', [$household, $target]), ['name' => 'cerveza'])
                ->assertRedirect();
        }

        $product = Product::query()->where('name', 'cerveza')->sole();

        $this->assertTrue($product->is_personal);
        $this->assertSame(1, Product::query()->count());
        $this->assertSame(1, ListItem::query()->where('list_id', $list->id)->where('product_id', $product->id)->count());
        $this->assertSame(1, ListItem::query()->where('list_id', $otherList->id)->where('product_id', $product->id)->count());
    }

    public function test_sharing_creator_quick_creates_a_personal_product_through_the_api(): void
    {
        $user = User::factory()->admin()->create(['shares_personal_products' => true]);

        $this->actingAs($user)
            ->postJson(route('api.v1.products.quick-create'), ['name' => 'Cerveza'])
            ->assertCreated();

        $this->assertTrue(Product::query()->where('name', 'Cerveza')->sole()->is_personal);
    }

    public function test_admin_index_groups_the_same_name_from_two_creators_into_one_row(): void
    {
        $ana = $this->sharingCreator('Ana');
        $luis = $this->sharingCreator('Luis');

        $oldest = $this->personalProduct($ana, 'Cerveza', ['created_at' => now()->subDays(2)]);
        $this->personalProduct($luis, 'cerveza');

        $this->actingAs($this->admin())
            ->get(route('admin.personal-products'))
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('admin/PersonalProducts/Index')
                ->has('products.data', 1)
                ->where('products.data.0.id', $oldest->id)
                ->where('products.data.0.name', 'Cerveza')
                ->where('products.data.0.users', 2)
                ->where('products.total', 1));
    }

    public function test_admin_index_ignores_case_accents_and_surrounding_whitespace_when_grouping(): void
    {
        $ana = $this->sharingCreator('Ana');
        $luis = $this->sharingCreator('Luis');
        $marta = $this->sharingCreator('Marta');

        $this->personalProduct($ana, 'Café con leche', ['created_at' => now()->subDays(2)]);
        $this->personalProduct($luis, '  cafe   CON leche ');
        $this->personalProduct($marta, 'CAFÉ CON LECHE');

        $this->actingAs($this->admin())
            ->get(route('admin.personal-products'))
            ->assertInertia(fn (Assert $page): Assert => $page
                ->has('products.data', 1)
                ->where('products.data.0.users', 3));
    }

    public function test_admin_index_paginates_groups_and_counts_them_across_the_filtered_set(): void
    {
        $ana = $this->sharingCreator('Ana');
        $luis = $this->sharingCreator('Luis');

        foreach (['Cerveza', 'Pan', 'Leche', 'Arroz', 'Café'] as $name) {
            $this->personalProduct($ana, $name);
        }

        // A second creator of an existing name must not add a page row.
        $this->personalProduct($luis, 'cerveza');

        $this->actingAs($this->admin())
            ->get(route('admin.personal-products', ['perPage' => 2]))
            ->assertInertia(fn (Assert $page): Assert => $page
                ->has('products.data', 2)
                ->where('products.total', 5)
                ->where('products.last_page', 3)
                ->where('products.per_page', 2));

        $this->actingAs($this->admin())
            ->get(route('admin.personal-products', ['perPage' => 2, 'page' => 3]))
            ->assertInertia(fn (Assert $page): Assert => $page
                ->has('products.data', 1)
                ->where('products.total', 5));
    }

    public function test_admin_index_search_filters_groups(): void
    {
        $ana = $this->sharingCreator('Ana');
        $luis = $this->sharingCreator('Luis');

        $this->personalProduct($ana, 'Cerveza');
        $this->personalProduct($luis, 'cerveza');
        $this->personalProduct($ana, 'Pan');

        $this->actingAs($this->admin())
            ->get(route('admin.personal-products', ['search' => 'Cerve']))
            ->assertInertia(fn (Assert $page): Assert => $page
                ->has('products.data', 1)
                ->where('products.data.0.name', 'Cerveza')
                ->where('products.data.0.users', 2)
                ->where('products.total', 1));
    }

    public function test_promoting_a_group_leaves_one_global_product_and_removes_the_name_duplicates(): void
    {
        $admin = $this->admin();
        $ana = $this->sharingCreator('Ana');
        $luis = $this->sharingCreator('Luis');

        $canonical = $this->personalProduct($ana, 'Cerveza', [
            'slug' => 'cerveza',
            'created_at' => now()->subDays(2),
        ]);
        $duplicate = $this->personalProduct($luis, 'cerveza', ['slug' => 'cerveza-luis']);

        $canonicalItem = ListItem::factory()->create(['product_id' => $canonical->id]);
        $duplicateItem = ListItem::factory()->create(['product_id' => $duplicate->id]);
        $ingredient = RecipeIngredient::factory()->create(['product_id' => $duplicate->id]);

        $this->actingAs($admin)
            ->post(route('admin.personal-products.promote', $canonical))
            ->assertRedirect(route('admin.products'));

        $global = Product::query()->where('is_personal', false)->sole();

        $this->assertSame('Cerveza', $global->name);
        $this->assertSame(0, Product::query()->where('is_personal', true)->count());
        $this->assertDatabaseHas('list_items', ['id' => $canonicalItem->id, 'product_id' => $global->id]);
        $this->assertDatabaseHas('list_items', ['id' => $duplicateItem->id, 'product_id' => $global->id]);
        $this->assertDatabaseHas('recipe_ingredients', ['id' => $ingredient->id, 'product_id' => $global->id]);

        $this->actingAs($admin)
            ->get(route('admin.personal-products'))
            ->assertInertia(fn (Assert $page): Assert => $page
                ->has('products.data', 0)
                ->where('products.total', 0));
    }

    public function test_promoting_a_group_removes_accent_variants_the_fuzzy_merge_cannot_match(): void
    {
        $admin = $this->admin();
        $ana = $this->sharingCreator('Ana');
        $luis = $this->sharingCreator('Luis');

        $canonical = $this->personalProduct($ana, 'Café', ['created_at' => now()->subDays(2)]);
        $this->personalProduct($luis, 'cafe');

        $this->actingAs($admin)
            ->post(route('admin.personal-products.promote', $canonical))
            ->assertRedirect(route('admin.products'));

        $this->assertSame(1, Product::query()->count());
        $this->assertFalse(Product::query()->sole()->is_personal);
    }

    /**
     * @return array{User, Household, ShoppingList}
     */
    private function householdContext(bool $shares): array
    {
        $user = User::factory()->admin()->create(['shares_personal_products' => $shares]);
        $household = Household::factory()->create(['created_by' => $user->id]);
        HouseholdMember::factory()->create([
            'user_id' => $user->id,
            'household_id' => $household->id,
        ]);
        $user->update(['active_household_id' => $household->id]);

        $list = ShoppingList::factory()->create([
            'household_id' => $household->id,
            'created_by' => $user->id,
        ]);

        return [$user, $household, $list];
    }

    private function admin(): User
    {
        return User::factory()->admin()->create();
    }

    private function sharingCreator(string $name): User
    {
        return User::factory()->create([
            'name' => $name,
            'shares_personal_products' => true,
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function personalProduct(User $creator, string $name, array $attributes = []): Product
    {
        return Product::factory()->create([
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::random(8),
            'is_personal' => true,
            'created_by' => $creator->id,
            ...$attributes,
        ]);
    }
}
