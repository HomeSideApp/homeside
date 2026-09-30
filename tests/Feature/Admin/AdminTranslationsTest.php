<?php

namespace Tests\Feature\Admin;

use App\Enums\TranslationEntityStatus;
use App\Enums\TranslationFieldStatus;
use App\Models\Category;
use App\Models\Permission;
use App\Models\PermissionGroup;
use App\Models\Product;
use App\Models\Role;
use App\Models\Translation;
use App\Models\TranslationStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class AdminTranslationsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $adminGroup = PermissionGroup::create(['name' => 'Admin']);

        Permission::create(['name' => 'view translations', 'route_name' => 'admin.translations', 'description' => 'Ver traducciones', 'permission_group_id' => $adminGroup->id]);
        Permission::create(['name' => 'store translations', 'route_name' => 'admin.translations.store', 'description' => 'Crear traducciones', 'permission_group_id' => $adminGroup->id]);
        Permission::create(['name' => 'update translations', 'route_name' => 'admin.translations.update', 'description' => 'Actualizar traducciones', 'permission_group_id' => $adminGroup->id]);
        Permission::create(['name' => 'delete translations', 'route_name' => 'admin.translations.destroy', 'description' => 'Eliminar traducciones', 'permission_group_id' => $adminGroup->id]);
        Permission::create(['name' => 'publish translations', 'route_name' => 'admin.translations.publish', 'description' => 'Publicar traducciones', 'permission_group_id' => $adminGroup->id]);
    }

    private function createAdmin(): User
    {
        $admin = User::factory()->create();
        $role = Role::create(['name' => 'admin', 'slug' => 'admin']);
        $role->givePermissionTo(Permission::all());
        $admin->assignRole($role);

        return $admin;
    }

    private function createRegularUser(): User
    {
        $user = User::factory()->create();
        $role = Role::create(['name' => 'user', 'slug' => 'user']);
        $user->assignRole($role);

        return $user;
    }

    private function makeCategory(string $name): Category
    {
        return Category::create(['name' => $name, 'slug' => Str::slug($name), 'color' => '#FF0000']);
    }

    private function makeTranslation(Category $category, string $value): Translation
    {
        return $category->putTranslation('name', 'es-ES', $value, TranslationFieldStatus::Approved);
    }

    private function makeStatus(Category $category, string $status): TranslationStatus
    {
        /** @var TranslationStatus $row */
        $row = $category->translationStatus()->firstOrCreate(
            ['locale' => 'es-ES'],
            ['status' => $status],
        );

        return $row;
    }

    // === Index ===

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('admin.translations'))->assertRedirect(route('login'));
    }

    public function test_user_without_permission_is_forbidden(): void
    {
        $user = $this->createRegularUser();

        $this->actingAs($user)->get(route('admin.translations'))->assertForbidden();
    }

    public function test_admin_sees_the_panel_with_default_filters(): void
    {
        $admin = $this->createAdmin();
        Category::create(['name' => 'Fruits', 'slug' => 'fruits', 'color' => '#FF0000']);

        $response = $this->actingAs($admin)->get(route('admin.translations'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('admin/Translations/Index')
            ->has('entities.data', 1)
            ->where('entities.data.0.name', 'Fruits')
            ->where('entities.data.0.type', 'category')
            ->where('filters.type', 'category')
            ->where('filters.locale', 'es-ES')
            ->where('filters.status', 'all'));
    }

    public function test_admin_can_select_a_new_translation_locale_before_saving_it(): void
    {
        $admin = $this->createAdmin();
        $this->makeCategory('Fruits');

        $response = $this->actingAs($admin)->get(route('admin.translations', ['locale' => 'pt-BR']));

        $response->assertInertia(fn ($page) => $page
            ->component('admin/Translations/Index')
            ->where('filters.locale', 'pt-BR')
            ->where('entities.data.0.translations.pt-BR', null)
            ->where('supportedLocales', fn ($locales) => collect($locales)->contains('code', 'pt-BR')));
    }

    public function test_saved_translation_locale_appears_in_the_selector(): void
    {
        $admin = $this->createAdmin();
        $category = $this->makeCategory('Fruits');
        $category->putTranslation('name', 'pt-BR', 'Frutas', TranslationFieldStatus::Approved);

        $response = $this->actingAs($admin)->get(route('admin.translations', ['locale' => 'pt-BR']));

        $response->assertInertia(fn ($page) => $page
            ->where('filters.locale', 'pt-BR')
            ->where('entities.data.0.translations.pt-BR.value', 'Frutas')
            ->where('supportedLocales', fn ($locales) => collect($locales)->contains('code', 'pt-BR')));
    }

    public function test_index_shows_translation_and_status_per_row(): void
    {
        $admin = $this->createAdmin();
        $category = Category::create(['name' => 'Fruits', 'slug' => 'fruits', 'color' => '#FF0000']);
        $this->makeTranslation($category, 'Frutas');
        $category->recalculateTranslationStatus('es-ES');

        $response = $this->actingAs($admin)->get(route('admin.translations'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('entities.data.0.translations.es-ES.value', 'Frutas')
            ->where('entities.data.0.is_complete', true)
            ->where('entities.data.0.coverage', ['completed' => 1, 'total' => 1])
            ->where('entities.data.0.status', TranslationEntityStatus::Complete->value));
    }

    // === Store ===

    public function test_admin_can_store_a_translation_and_reaches_complete(): void
    {
        $admin = $this->createAdmin();
        $category = Category::create(['name' => 'Fruits', 'slug' => 'fruits', 'color' => '#FF0000']);

        $response = $this->actingAs($admin)->post(route('admin.translations.store'), [
            'translatable_type' => 'category',
            'translatable_id' => $category->id,
            'locale' => 'es-ES',
            'field' => 'name',
            'value' => 'Frutas',
        ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('translations', [
            'translatable_type' => 'category',
            'translatable_id' => $category->id,
            'locale' => 'es-ES',
            'field' => 'name',
            'value' => 'Frutas',
        ]);

        $this->assertSame(
            TranslationEntityStatus::Complete,
            $category->translationStatus()->forLocale('es-ES')->firstOrFail()->status,
        );
        $response->assertInertiaFlash('toast', ['type' => 'success', 'message' => __('app.toast.translation_saved')]);
    }

    public function test_store_rejects_invalid_locale(): void
    {
        $admin = $this->createAdmin();
        $category = Category::create(['name' => 'Fruits', 'slug' => 'fruits', 'color' => '#FF0000']);

        $this->actingAs($admin)->post(route('admin.translations.store'), [
            'translatable_type' => 'category',
            'translatable_id' => $category->id,
            'locale' => 'XX-!!!',
            'field' => 'name',
            'value' => 'Fruits',
        ])->assertSessionHasErrors('locale');
    }

    public function test_store_accepts_free_locale(): void
    {
        $admin = $this->createAdmin();
        $category = Category::create(['name' => 'Fruits', 'slug' => 'fruits', 'color' => '#FF0000']);

        $this->actingAs($admin)->post(route('admin.translations.store'), [
            'translatable_type' => 'category',
            'translatable_id' => $category->id,
            'locale' => 'pt-BR',
            'field' => 'name',
            'value' => 'Frutas',
        ])->assertValid();

        $this->assertDatabaseHas('translations', ['locale' => 'pt-BR', 'value' => 'Frutas']);
    }

    public function test_store_rejects_invalid_type(): void
    {
        $admin = $this->createAdmin();
        $category = Category::create(['name' => 'Fruits', 'slug' => 'fruits', 'color' => '#FF0000']);

        $this->actingAs($admin)->post(route('admin.translations.store'), [
            'translatable_type' => 'rocket',
            'translatable_id' => $category->id,
            'locale' => 'es-ES',
            'field' => 'name',
            'value' => 'Fruits',
        ])->assertSessionHasErrors('translatable_type');
    }

    public function test_store_rejects_unknown_field(): void
    {
        $admin = $this->createAdmin();
        $category = Category::create(['name' => 'Fruits', 'slug' => 'fruits', 'color' => '#FF0000']);

        $this->actingAs($admin)->post(route('admin.translations.store'), [
            'translatable_type' => 'category',
            'translatable_id' => $category->id,
            'locale' => 'es-ES',
            'field' => 'slug',
            'value' => 'fruits',
        ])->assertSessionHasErrors('field');
    }

    // === Update ===

    public function test_admin_can_update_a_translation(): void
    {
        $admin = $this->createAdmin();
        $category = Category::create(['name' => 'Fruits', 'slug' => 'fruits', 'color' => '#FF0000']);
        $translation = $this->makeTranslation($category, 'Frutas');

        $this->actingAs($admin)->put(route('admin.translations.update', $translation), [
            'value' => 'Frutas actualizada',
        ])->assertRedirect();

        $translation->refresh();
        $this->assertSame('Frutas actualizada', $translation->value);
        $this->assertSame(
            TranslationEntityStatus::Complete,
            $category->translationStatus()->forLocale('es-ES')->firstOrFail()->status,
        );
    }

    // === Destroy ===

    public function test_admin_can_delete_a_translation_and_status_downgrades(): void
    {
        $admin = $this->createAdmin();
        $category = Category::create(['name' => 'Fruits', 'slug' => 'fruits', 'color' => '#FF0000']);
        $translation = $this->makeTranslation($category, 'Frutas');
        $category->recalculateTranslationStatus('es-ES');

        $this->actingAs($admin)->delete(route('admin.translations.destroy', $translation))->assertRedirect();

        $this->assertDatabaseMissing('translations', ['id' => $translation->id]);
        $this->assertSame(
            TranslationEntityStatus::Incomplete,
            $category->translationStatus()->forLocale('es-ES')->firstOrFail()->status,
        );
    }

    // === Publish / unpublish ===

    public function test_admin_can_publish_a_complete_translation(): void
    {
        $admin = $this->createAdmin();
        $category = Category::create(['name' => 'Fruits', 'slug' => 'fruits', 'color' => '#FF0000']);
        $this->makeTranslation($category, 'Frutas');
        $category->recalculateTranslationStatus('es-ES');

        $this->actingAs($admin)->post(route('admin.translations.publish'), [
            'translatable_type' => 'category',
            'translatable_id' => $category->id,
            'locale' => 'es-ES',
            'publish' => true,
        ])->assertRedirect();

        $status = $category->translationStatus()->forLocale('es-ES')->firstOrFail();
        $this->assertSame(TranslationEntityStatus::Published, $status->status);
        $this->assertNotNull($status->published_at);
    }

    public function test_publish_fails_when_translation_is_not_complete(): void
    {
        $admin = $this->createAdmin();
        $category = Category::create(['name' => 'Fruits', 'slug' => 'fruits', 'color' => '#FF0000']);
        Translation::create([
            'translatable_type' => 'category',
            'translatable_id' => $category->id,
            'locale' => 'es-ES',
            'field' => 'description',
            'value' => 'Wrong field',
        ]);
        $category->recalculateTranslationStatus('es-ES');

        $this->actingAs($admin)->post(route('admin.translations.publish'), [
            'translatable_type' => 'category',
            'translatable_id' => $category->id,
            'locale' => 'es-ES',
            'publish' => true,
        ])->assertSessionHasErrors('publish');
    }

    public function test_admin_can_unpublish_a_translation(): void
    {
        $admin = $this->createAdmin();
        $category = Category::create(['name' => 'Fruits', 'slug' => 'fruits', 'color' => '#FF0000']);
        $this->makeTranslation($category, 'Frutas');
        $this->makeStatus($category, TranslationEntityStatus::Published->value);

        $this->actingAs($admin)->post(route('admin.translations.publish'), [
            'translatable_type' => 'category',
            'translatable_id' => $category->id,
            'locale' => 'es-ES',
            'publish' => false,
        ])->assertRedirect();

        $status = $category->translationStatus()->forLocale('es-ES')->firstOrFail();
        $this->assertSame(TranslationEntityStatus::Incomplete, $status->status);
        $this->assertNull($status->published_at);
    }

    public function test_bulk_publish_only_publishes_selected_rows(): void
    {
        $admin = $this->createAdmin();
        $selected = $this->makeCategory('Fruits');
        $other = $this->makeCategory('Vegetables');

        foreach ([$selected, $other] as $category) {
            $this->makeTranslation($category, $category->name.' translated');
            $category->recalculateTranslationStatus('es-ES');
        }

        $this->actingAs($admin)->post(route('admin.translations.publish-bulk'), [
            'mode' => 'selected',
            'translatable_type' => 'category',
            'locale' => 'es-ES',
            'ids' => [$selected->id],
        ])->assertRedirect();

        $this->assertSame(TranslationEntityStatus::Published, $selected->translationStatus()->forLocale('es-ES')->firstOrFail()->status);
        $this->assertSame(TranslationEntityStatus::Complete, $other->translationStatus()->forLocale('es-ES')->firstOrFail()->status);
    }

    public function test_bulk_publish_applies_to_all_filtered_results_across_pages(): void
    {
        $admin = $this->createAdmin();
        $first = $this->makeCategory('Fruit A');
        $second = $this->makeCategory('Fruit B');
        $other = $this->makeCategory('Vegetables');

        foreach ([$first, $second, $other] as $category) {
            $this->makeTranslation($category, $category->name.' translated');
            $category->recalculateTranslationStatus('es-ES');
        }

        $this->actingAs($admin)
            ->get(route('admin.translations', ['search' => 'Fruit', 'perPage' => 1]))
            ->assertInertia(fn ($page) => $page->has('entities.data', 1)->where('entities.total', 2));

        $this->actingAs($admin)->post(route('admin.translations.publish-bulk'), [
            'mode' => 'filtered',
            'translatable_type' => 'category',
            'locale' => 'es-ES',
            'search' => 'Fruit',
            'status' => 'complete',
            'page' => 2,
            'perPage' => 1,
        ])->assertRedirect();

        $this->assertSame(TranslationEntityStatus::Published, $first->translationStatus()->forLocale('es-ES')->firstOrFail()->status);
        $this->assertSame(TranslationEntityStatus::Published, $second->translationStatus()->forLocale('es-ES')->firstOrFail()->status);
        $this->assertSame(TranslationEntityStatus::Complete, $other->translationStatus()->forLocale('es-ES')->firstOrFail()->status);
    }

    public function test_bulk_publish_skips_incomplete_and_already_published_rows(): void
    {
        $admin = $this->createAdmin();
        $pending = $this->makeCategory('Fruit A');
        $published = $this->makeCategory('Fruit B');
        $incomplete = $this->makeCategory('Fruit C');

        $this->makeTranslation($pending, 'Fruta A');
        $pending->recalculateTranslationStatus('es-ES');
        $this->makeTranslation($published, 'Fruta B');
        $this->makeStatus($published, TranslationEntityStatus::Published->value);

        $response = $this->actingAs($admin)->post(route('admin.translations.publish-bulk'), [
            'mode' => 'filtered',
            'translatable_type' => 'category',
            'locale' => 'es-ES',
        ]);

        $response->assertRedirect();
        $response->assertInertiaFlash('toast', [
            'type' => 'success',
            'message' => __('app.toast.translations_bulk_published', ['published' => 1, 'skipped' => 2]),
        ]);

        $this->assertSame(TranslationEntityStatus::Published, $pending->translationStatus()->forLocale('es-ES')->firstOrFail()->status);
        $this->assertSame(TranslationEntityStatus::Published, $published->translationStatus()->forLocale('es-ES')->firstOrFail()->status);
        $this->assertDatabaseMissing('translation_statuses', ['translatable_id' => $incomplete->id, 'locale' => 'es-ES']);
    }

    public function test_bulk_publish_requires_selection_and_permission(): void
    {
        $user = $this->createRegularUser();
        $admin = $this->createAdmin();
        $payload = [
            'mode' => 'selected',
            'translatable_type' => 'category',
            'locale' => 'es-ES',
            'ids' => [],
        ];

        $this->actingAs($user)->post(route('admin.translations.publish-bulk'), $payload)->assertForbidden();
        $this->actingAs($admin)->post(route('admin.translations.publish-bulk'), $payload)->assertSessionHasErrors('ids');
    }

    // === Filtros ===

    public function test_index_filters_by_published_status(): void
    {
        $admin = $this->createAdmin();

        $publishedCategory = Category::create(['name' => 'Fruits', 'slug' => 'fruits', 'color' => '#FF0000']);
        $this->makeTranslation($publishedCategory, 'Frutas');
        $this->makeStatus($publishedCategory, TranslationEntityStatus::Published->value);

        Category::create(['name' => 'Vegetables', 'slug' => 'vegetables', 'color' => '#00FF00']);

        $response = $this->actingAs($admin)->get(route('admin.translations', ['status' => 'published']));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->has('entities.data', 1)
            ->where('entities.data.0.name', 'Fruits'));
    }

    public function test_index_filters_untranslated_entities(): void
    {
        $admin = $this->createAdmin();

        $translated = Category::create(['name' => 'Fruits', 'slug' => 'fruits', 'color' => '#FF0000']);
        $this->makeTranslation($translated, 'Frutas');

        Category::create(['name' => 'Vegetables', 'slug' => 'vegetables', 'color' => '#00FF00']);

        $response = $this->actingAs($admin)->get(route('admin.translations', ['status' => 'untranslated']));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->has('entities.data', 1)
            ->where('entities.data.0.name', 'Vegetables'));
    }

    public function test_index_searches_by_source_name(): void
    {
        $admin = $this->createAdmin();
        Category::create(['name' => 'Fruits', 'slug' => 'fruits', 'color' => '#FF0000']);
        Category::create(['name' => 'Vegetables', 'slug' => 'vegetables', 'color' => '#00FF00']);

        $response = $this->actingAs($admin)->get(route('admin.translations', ['search' => 'Fruit']));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->has('entities.data', 1)
            ->where('entities.data.0.name', 'Fruits'));
    }

    public function test_index_supports_other_entity_types(): void
    {
        $admin = $this->createAdmin();
        Product::factory()->create(['name' => 'Milk']);

        $response = $this->actingAs($admin)->get(route('admin.translations', ['type' => 'product']));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->has('entities.data', 1)
            ->where('entities.data.0.type', 'product')
            ->where('entities.data.0.name', 'Milk'));
    }
}
