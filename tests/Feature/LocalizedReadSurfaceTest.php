<?php

namespace Tests\Feature;

use App\Actions\Translations\PublishTranslation;
use App\Data\Translations\PublishTranslationData;
use App\Enums\TranslationFieldStatus;
use App\Models\Category;
use App\Models\Household;
use App\Models\HouseholdMember;
use App\Models\HouseholdModule;
use App\Models\Permission;
use App\Models\PermissionGroup;
use App\Models\Product;
use App\Models\Role;
use App\Models\ShoppingList;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Verifies the user-facing read surface (API resources + admin exclusion).
 *
 * User-facing endpoints localize `name` only when a translation is published
 * for the request locale; admin endpoints always show the source column.
 */
class LocalizedReadSurfaceTest extends TestCase
{
    use RefreshDatabase;

    protected function publish(Category|Product $entity, string $locale): void
    {
        app(PublishTranslation::class)->execute(new PublishTranslationData(
            translatableType: $entity->translatableType(),
            translatableId: $entity->id,
            locale: $locale,
            publish: true,
        ));
    }

    protected function translate(Category|Product $entity, string $locale, string $value): void
    {
        $entity->putTranslation('name', $locale, $value, TranslationFieldStatus::Approved);
        $entity->recalculateTranslationStatus($locale);
    }

    protected function actingSpanishUser(): User
    {
        $group = PermissionGroup::create(['name' => 'Listas']);
        Permission::create([
            'name' => 'view lists',
            'route_name' => 'lists.index',
            'description' => 'Ver listas',
            'permission_group_id' => $group->id,
        ]);
        $role = Role::create(['name' => 'user', 'slug' => 'user']);
        $role->syncPermissions(['view lists']);

        $user = User::factory()->create(['locale' => 'es-ES']);
        $user->assignRole($role);
        Sanctum::actingAs($user);

        return $user;
    }

    public function test_categories_index_returns_published_translation_for_user_locale(): void
    {
        $this->actingSpanishUser();

        Category::create(['name' => 'Fruits', 'slug' => 'fruits', 'color' => '#FF0000', 'sort_order' => 1]);
        $translated = Category::create(['name' => 'Vegetables', 'slug' => 'vegetables', 'color' => '#00FF00', 'sort_order' => 2]);
        $this->translate($translated, 'es-ES', 'Verduras');
        $this->publish($translated, 'es-ES');

        $response = $this->getJson(route('api.v1.categories.index'));

        $response->assertOk()
            ->assertJsonPath('data.0.name', 'Fruits')
            ->assertJsonPath('data.1.name', 'Verduras');
    }

    public function test_categories_index_returns_source_when_translation_is_not_published(): void
    {
        $this->actingSpanishUser();

        $category = Category::create(['name' => 'Fruits', 'slug' => 'fruits', 'color' => '#FF0000', 'sort_order' => 1]);
        // Complete but unpublished translation for es-ES.
        $this->translate($category, 'es-ES', 'Frutas');

        $this->getJson(route('api.v1.categories.index'))
            ->assertOk()
            ->assertJsonPath('data.0.name', 'Fruits');
    }

    public function test_categories_index_localizes_only_published_entities_in_the_same_list(): void
    {
        $this->actingSpanishUser();

        $published = Category::create(['name' => 'Fruits', 'slug' => 'fruits', 'color' => '#FF0000', 'sort_order' => 1]);
        $this->translate($published, 'es-ES', 'Frutas');
        $this->publish($published, 'es-ES');

        $unpublished = Category::create(['name' => 'Vegetables', 'slug' => 'vegetables', 'color' => '#00FF00', 'sort_order' => 2]);
        $this->translate($unpublished, 'es-ES', 'Verduras');

        $response = $this->getJson(route('api.v1.categories.index'));

        $response->assertOk()
            ->assertJsonPath('data.0.name', 'Frutas')
            ->assertJsonPath('data.1.name', 'Vegetables');
    }

    public function test_product_search_returns_published_translation_for_user_locale(): void
    {
        $user = $this->actingSpanishUser();

        $product = Product::create(['name' => 'Milk', 'slug' => 'milk', 'created_by' => $user->id]);
        $this->translate($product, 'es-ES', 'Leche');
        $this->publish($product, 'es-ES');

        $this->getJson(route('api.v1.products.search', ['q' => 'Milk']))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Leche');
    }

    public function test_product_search_returns_source_when_translation_is_not_published(): void
    {
        $user = $this->actingSpanishUser();

        $product = Product::create(['name' => 'Milk', 'slug' => 'milk', 'created_by' => $user->id]);
        $this->translate($product, 'es-ES', 'Leche');

        $this->getJson(route('api.v1.products.search', ['q' => 'Milk']))
            ->assertOk()
            ->assertJsonPath('data.0.name', 'Milk');
    }

    public function test_products_index_does_not_query_translations_per_model(): void
    {
        $this->actingSpanishUser();

        foreach (['Alpha', 'Beta', 'Gamma', 'Delta'] as $name) {
            $product = Product::create(['name' => $name, 'slug' => strtolower($name), 'created_by' => auth()->id() ?? null]);
            $this->translate($product, 'es-ES', 'ES '.$name);
            $this->publish($product, 'es-ES');
        }

        DB::enableQueryLog();

        $response = $this->getJson(route('api.v1.products.index', ['perPage' => 20]));

        $queryCount = count(DB::getQueryLog());
        DB::disableQueryLog();

        $response->assertOk();
        $names = collect($response->json('data'))->pluck('name');
        $this->assertSame(['ES Alpha', 'ES Beta', 'ES Delta', 'ES Gamma'], $names->all());
        // 1 for products, 1 for categories, 2 for product translations+statuses,
        // 2 for category translations+statuses — never per-model queries.
        $this->assertLessThanOrEqual(6, $queryCount);
    }

    public function test_admin_product_pending_endpoint_returns_source_names(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $admin = User::factory()->create(['locale' => 'es-ES']);
        $admin->assignRole('admin');
        Sanctum::actingAs($admin);

        $product = Product::factory()->pending()->create(['name' => 'Milk']);
        $this->translate($product, 'es-ES', 'Leche');
        $this->publish($product, 'es-ES');

        $this->getJson(route('api.v1.admin.products.pending.index'))
            ->assertOk()
            ->assertJsonPath('data.0.name', 'Milk');
    }

    public function test_shopping_list_show_renders_with_localized_catalog(): void
    {
        $user = $this->actingSpanishUser();

        $category = Category::create(['name' => 'Dairy', 'slug' => 'dairy', 'color' => '#FFFFFF', 'sort_order' => 1]);
        $this->translate($category, 'es-ES', 'Lácteos');
        $this->publish($category, 'es-ES');

        $product = Product::create(['name' => 'Milk', 'slug' => 'milk', 'category_id' => $category->id, 'created_by' => $user->id]);
        $this->translate($product, 'es-ES', 'Leche');
        $this->publish($product, 'es-ES');

        $group = PermissionGroup::create(['name' => 'Listas']);
        Permission::create([
            'name' => 'view list',
            'route_name' => 'households.lists.show',
            'description' => 'Ver una lista',
            'permission_group_id' => $group->id,
        ]);
        $role = Role::create(['name' => 'list-viewer', 'slug' => 'list-viewer']);
        $role->syncPermissions(['view list']);
        $user->assignRole($role);

        $household = Household::factory()->create(['created_by' => $user->id]);
        HouseholdMember::create([
            'user_id' => $user->id,
            'household_id' => $household->id,
            'role' => 'admin',
            'joined_at' => now(),
        ]);
        $user->update(['active_household_id' => $household->id]);

        HouseholdModule::updateOrCreate(
            ['household_id' => $household->id, 'module' => 'shopping_lists'],
            ['enabled' => true],
        );
        $list = ShoppingList::factory()->create([
            'name' => 'Groceries',
            'household_id' => $household->id,
            'created_by' => $user->id,
        ]);
        $list->items()->create(['product_id' => $product->id, 'quantity' => 1, 'added_by' => $user->id]);

        // Regression: this route crashed with BadMethodCallException when the
        // eager load used the dot-scope `with('category.withTranslationData')`.
        $response = $this->get(route('households.lists.show', [$household, $list]));

        $response->assertOk();
        $this->assertStringContainsString('Lácteos', (string) $response->getContent());
    }
}
