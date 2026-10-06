<?php

namespace Tests\Feature;

use App\Enums\TranslationEntityStatus;
use App\Enums\TranslationFieldStatus;
use App\Http\Middleware\HandleInertiaRequests;
use App\Models\Category;
use App\Models\Household;
use App\Models\HouseholdMember;
use App\Models\ListItem;
use App\Models\Product;
use App\Models\ProductUsage;
use App\Models\ShoppingList;
use App\Models\Store;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class ShoppingListTranslationsTest extends TestCase
{
    use RefreshDatabase;

    #[TestWith([false, 'es-ES', 'Pan'])]
    #[TestWith([true, 'es-ES', 'Pan'])]
    #[TestWith([false, 'en-US', 'Bread'])]
    public function test_list_items_and_recent_products_use_the_users_locale(bool $shared, string $locale, string $name): void
    {
        [$user, $list] = $this->listContext($shared, $locale);
        $category = Category::factory()->create(['name' => 'Bakery']);
        $store = Store::factory()->create(['name' => 'Shop']);
        $product = $this->bread($user, $category);
        $this->publishName($category, 'Panadería');
        $this->publishName($store, 'Tienda');
        $item = ListItem::factory()->create([
            'list_id' => $list->id, 'product_id' => $product->id,
            'category_id' => $category->id, 'store_id' => $store->id,
            'image_url' => 'list-items/bread.jpg',
        ]);
        ProductUsage::create(['list_id' => $list->id, 'product_id' => $product->id, 'user_id' => $user->id, 'last_used_at' => now()]);

        $this->actingAs($user)->get($this->listRoute($list, 'show'))
            ->assertOk()->assertInertia(fn (Assert $page) => $page->component('lists/Show')
            ->where('list.items.0.product.name', $name)
            ->where('list.items.0.product.category.name', $locale === 'es-ES' ? 'Panadería' : 'Bakery')
            ->where('list.items.0.category.name', $locale === 'es-ES' ? 'Panadería' : 'Bakery')
            ->where('list.items.0.store.name', $locale === 'es-ES' ? 'Tienda' : 'Shop')
            ->where('list.items.0.image_url', $shared
                ? route('households.lists.items.image', [$list->household_id, $list, $item])
                : route('lists.items.image', [$list, $item]))
            ->where('recentProducts.0.name', $name)
            ->where('stores.0.name', $locale === 'es-ES' ? 'Tienda' : 'Shop'));

        $this->getJson($this->listRoute($list, 'show', api: true))->assertOk()
            ->assertJsonPath('data.items.0.product.name', $name);
        $this->assertSame('Bread', $product->refresh()->name);
    }

    #[TestWith([false])]
    #[TestWith([true])]
    public function test_search_finds_published_translated_names_in_web_and_api(bool $shared): void
    {
        [$user, $list] = $this->listContext($shared);
        $product = $this->bread($user);

        $this->actingAs($user)->get($this->listRoute($list, 'search-products').'?q=Pan', $this->inertiaHeaders())
            ->assertOk()->assertJsonCount(1, 'props.searchResults')
            ->assertJsonPath('props.searchResults.0.id', $product->id)
            ->assertJsonPath('props.searchResults.0.name', 'Pan');
        $this->getJson(route('api.products.search', ['q' => 'Pan']))->assertOk()
            ->assertJsonCount(1)->assertJsonPath('0.name', 'Pan');
        $this->getJson(route('api.v1.products.search', ['q' => 'Pan']))->assertOk()
            ->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $product->id)
            ->assertJsonPath('data.0.name', 'Pan');
    }

    #[TestWith([false])]
    #[TestWith([true])]
    public function test_quick_create_reuses_translated_product_without_changing_its_source_name(bool $shared): void
    {
        [$user, $list] = $this->listContext($shared);
        $product = $this->bread($user);

        $this->actingAs($user)->post($this->listRoute($list, 'search-products.quick-create'), ['name' => 'Pan'])
            ->assertRedirect($this->listRoute($list, 'show'));

        $this->assertDatabaseCount('products', 1);
        $this->assertDatabaseHas('list_items', ['list_id' => $list->id, 'product_id' => $product->id, 'quantity' => 1]);
        $this->assertDatabaseHas('products', ['id' => $product->id, 'name' => 'Bread', 'slug' => 'bread']);
        $this->get($this->listRoute($list, 'show'))->assertInertia(fn (Assert $page) => $page->where('list.items.0.product.name', 'Pan'));
        $this->postJson(route('api.v1.products.quick-create'), ['name' => 'Pan'])->assertCreated()
            ->assertJsonPath('data.id', $product->id)->assertJsonPath('data.name', 'Pan');
        $this->assertDatabaseCount('products', 1);
    }

    #[TestWith(['complete', 'es-ES'])]
    #[TestWith(['published', 'fr-FR'])]
    public function test_search_does_not_use_unpublished_names_or_unrelated_locales(string $status, string $locale): void
    {
        [$user, $list] = $this->listContext(false);
        $product = Product::factory()->create(['name' => 'Bread', 'created_by' => $user->id]);
        $product->putTranslation('name', $locale, 'Pan', TranslationFieldStatus::Approved);
        $product->translationStatus()->create(['locale' => $locale, 'status' => $status]);

        $this->actingAs($user)->get($this->listRoute($list, 'search-products').'?q=Pan', $this->inertiaHeaders())
            ->assertOk()->assertJsonCount(0, 'props.searchResults');
        $this->getJson(route('api.v1.products.search', ['q' => 'Pan']))->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_unpublished_translation_displays_source_name(): void
    {
        [$user, $list] = $this->listContext(false);
        $product = $this->bread($user);
        $product->translationStatus()->update(['status' => TranslationEntityStatus::Complete]);
        ListItem::factory()->create(['list_id' => $list->id, 'product_id' => $product->id]);

        $this->actingAs($user)->get($this->listRoute($list, 'show'))->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('list.items.0.product.name', 'Bread'));
    }

    public function test_quick_create_does_not_reuse_partial_translation_matches(): void
    {
        [$user, $list] = $this->listContext(false);
        $product = Product::factory()->create(['name' => 'Wholemeal bread', 'created_by' => $user->id]);
        $this->publishName($product, 'Pan integral');

        $this->actingAs($user)->post($this->listRoute($list, 'search-products.quick-create'), ['name' => 'Pan'])->assertRedirect();

        $this->assertDatabaseCount('products', 2);
        $created = Product::query()->where('name', 'Pan')->firstOrFail();
        $this->assertDatabaseHas('list_items', ['list_id' => $list->id, 'product_id' => $created->id]);
    }

    #[DataProvider('productNamesWithPrivateSlugCollisions')]
    public function test_translation_search_and_quick_create_never_select_someone_elses_personal_product(string $name, string $slug): void
    {
        [$user, $list] = $this->listContext(false);
        $foreign = Product::factory()->create(['name' => 'Bread', 'slug' => $slug, 'is_personal' => true]);
        $this->publishName($foreign, 'Pan');

        $this->actingAs($user)->get($this->listRoute($list, 'search-products').'?q=Pan', $this->inertiaHeaders())
            ->assertOk()->assertJsonCount(0, 'props.searchResults');
        $this->getJson(route('api.v1.products.search', ['q' => 'Pan']))->assertOk()->assertJsonCount(0, 'data');
        $this->post($this->listRoute($list, 'search-products.quick-create'), ['name' => $name])->assertRedirect();

        $this->assertDatabaseMissing('list_items', ['list_id' => $list->id, 'product_id' => $foreign->id]);
        $created = Product::query()->where('created_by', $user->id)->where('name', $name)->firstOrFail();
        $this->assertDatabaseHas('list_items', ['list_id' => $list->id, 'product_id' => $created->id]);
    }

    /** @return array<string, array{string, string}> */
    public static function productNamesWithPrivateSlugCollisions(): array
    {
        return [
            'short name' => ['Pan', 'pan'],
            'maximum length' => [str_repeat('P', 255), str_repeat('p', 255)],
        ];
    }

    public function test_search_and_list_use_published_fallback_when_active_locale_is_missing(): void
    {
        [$user, $list] = $this->listContext(false);
        $product = Product::factory()->create(['name' => 'Original name', 'created_by' => $user->id]);
        $this->publishName($product, 'Bread', 'en-US');
        ListItem::factory()->create(['list_id' => $list->id, 'product_id' => $product->id]);

        $this->actingAs($user)->get($this->listRoute($list, 'search-products').'?q=Bread', $this->inertiaHeaders())
            ->assertOk()->assertJsonPath('props.searchResults.0.name', 'Bread');
        $this->get($this->listRoute($list, 'show'))->assertInertia(fn (Assert $page) => $page->where('list.items.0.product.name', 'Bread'));
    }

    /** @return array{User, ShoppingList} */
    private function listContext(bool $shared, string $locale = 'es-ES'): array
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $user = User::factory()->admin()->create(['locale' => $locale]);
        $household = null;

        if ($shared) {
            $household = Household::factory()->create(['created_by' => $user->id]);
            HouseholdMember::factory()->create(['user_id' => $user->id, 'household_id' => $household->id]);
            $user->update(['active_household_id' => $household->id]);
        }

        return [$user, ShoppingList::factory()->create(['created_by' => $user->id, 'household_id' => $household?->id])];
    }

    private function bread(User $user, ?Category $category = null): Product
    {
        $product = Product::factory()->create(['name' => 'Bread', 'slug' => 'bread', 'created_by' => $user->id, 'category_id' => $category?->id]);
        $this->publishName($product, 'Pan');

        return $product;
    }

    private function publishName(Product|Category|Store $entity, string $name, string $locale = 'es-ES'): void
    {
        $entity->putTranslation('name', $locale, $name, TranslationFieldStatus::Approved);
        $entity->translationStatus()->create(['locale' => $locale, 'status' => TranslationEntityStatus::Published, 'published_at' => now()]);
    }

    private function listRoute(ShoppingList $list, string $action, bool $api = false): string
    {
        $prefix = $api ? 'api.v1.' : '';

        return $list->household_id === null
            ? route($prefix.'lists.'.$action, $list)
            : route($prefix.'households.lists.'.$action, [$list->household_id, $list]);
    }

    /** @return array<string, string|null> */
    private function inertiaHeaders(): array
    {
        return [
            'X-Inertia' => 'true',
            'X-Inertia-Version' => app(HandleInertiaRequests::class)->version(request()),
            'X-Inertia-Partial-Component' => 'lists/Show',
            'X-Inertia-Partial-Data' => 'searchResults',
        ];
    }
}
