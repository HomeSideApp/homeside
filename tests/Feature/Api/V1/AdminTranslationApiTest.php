<?php

namespace Tests\Feature\Api\V1;

use App\Enums\TranslationEntityStatus;
use App\Enums\TranslationFieldStatus;
use App\Models\Category;
use App\Models\Permission;
use App\Models\PermissionGroup;
use App\Models\Role;
use App\Models\TranslationStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminTranslationApiTest extends TestCase
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
        foreach (['index', 'store', 'update', 'destroy', 'publish'] as $action) {
            Permission::create(['name' => "translations api {$action}", 'route_name' => "api.v1.admin.translations.{$action}", 'description' => "API: traducciones ({$action})", 'permission_group_id' => $adminGroup->id]);
        }
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

    // === Autenticación y permisos ===

    public function test_guest_gets_unauthorized(): void
    {
        $this->getJson(route('api.v1.admin.translations.index'))->assertUnauthorized();
    }

    public function test_user_without_permission_is_forbidden(): void
    {
        $user = $this->createRegularUser();
        Sanctum::actingAs($user);

        $this->getJson(route('api.v1.admin.translations.index'))->assertForbidden();
        $this->postJson(route('api.v1.admin.translations.store'), [])->assertForbidden();
    }

    // === Index ===

    public function test_admin_can_list_translations(): void
    {
        $admin = $this->createAdmin();
        Sanctum::actingAs($admin);
        $category = $this->makeCategory('Fruits');
        $category->putTranslation('name', 'es-ES', 'Frutas', TranslationFieldStatus::Approved);

        $response = $this->getJson(route('api.v1.admin.translations.index'));

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'translatable_type', 'translatable_id', 'locale', 'field', 'value', 'status', 'created_at', 'updated_at'],
                ],
                'links',
                'meta',
            ])
            ->assertJsonPath('data.0.value', 'Frutas')
            ->assertJsonPath('data.0.translatable_type', 'category')
            ->assertJsonPath('data.0.locale', 'es-ES');
    }

    public function test_index_filters_by_type_locale_and_status(): void
    {
        $admin = $this->createAdmin();
        Sanctum::actingAs($admin);
        $category = $this->makeCategory('Fruits');
        $category->putTranslation('name', 'es-ES', 'Frutas', TranslationFieldStatus::Approved);
        $other = $this->makeCategory('Vegetables');
        $other->putTranslation('name', 'es-ES', 'Verduras', TranslationFieldStatus::Pending);

        $this->getJson(route('api.v1.admin.translations.index', ['status' => 'approved']))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.value', 'Frutas');

        $this->getJson(route('api.v1.admin.translations.index', ['locale' => 'en-US']))
            ->assertOk()
            ->assertJsonCount(0, 'data');

        $this->getJson(route('api.v1.admin.translations.index', ['type' => 'product']))
            ->assertOk()
            ->assertJsonCount(0, 'data');

        $this->getJson(route('api.v1.admin.translations.index', ['search' => 'Verduras']))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.value', 'Verduras');

        $this->getJson(route('api.v1.admin.translations.index', ['locale' => 'xx-XX']))
            ->assertStatus(422);
    }

    // === Store ===

    public function test_admin_can_store_a_translation_and_entity_becomes_complete(): void
    {
        $admin = $this->createAdmin();
        Sanctum::actingAs($admin);
        $category = $this->makeCategory('Fruits');

        $response = $this->postJson(route('api.v1.admin.translations.store'), [
            'translatable_type' => 'category',
            'translatable_id' => $category->id,
            'locale' => 'es-ES',
            'field' => 'name',
            'value' => 'Frutas',
            'status' => 'approved',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.value', 'Frutas')
            ->assertJsonPath('data.status', 'approved');

        $this->assertDatabaseHas('translations', [
            'translatable_type' => 'category',
            'translatable_id' => $category->id,
            'locale' => 'es-ES',
            'field' => 'name',
            'value' => 'Frutas',
        ]);
        $this->assertDatabaseHas('translation_statuses', [
            'translatable_type' => 'category',
            'translatable_id' => $category->id,
            'locale' => 'es-ES',
            'status' => TranslationEntityStatus::Complete->value,
        ]);
    }

    public function test_store_with_invalid_locale_fails_validation(): void
    {
        $admin = $this->createAdmin();
        Sanctum::actingAs($admin);
        $category = $this->makeCategory('Fruits');

        $response = $this->postJson(route('api.v1.admin.translations.store'), [
            'translatable_type' => 'category',
            'translatable_id' => $category->id,
            'locale' => 'not a locale',
            'field' => 'name',
            'value' => 'Frutas',
        ]);

        $response->assertStatus(422);
        $this->assertDatabaseCount('translations', 0);
    }

    public function test_store_accepts_free_locale(): void
    {
        $admin = $this->createAdmin();
        Sanctum::actingAs($admin);
        $category = $this->makeCategory('Fruits');

        $this->postJson(route('api.v1.admin.translations.store'), [
            'translatable_type' => 'category',
            'translatable_id' => $category->id,
            'locale' => 'pt-BR',
            'field' => 'name',
            'value' => 'Frutas',
        ])->assertCreated();

        $this->assertDatabaseHas('translations', ['locale' => 'pt-BR', 'value' => 'Frutas']);
    }

    // === Update ===

    public function test_admin_can_update_a_translation(): void
    {
        $admin = $this->createAdmin();
        Sanctum::actingAs($admin);
        $category = $this->makeCategory('Fruits');
        $translation = $category->putTranslation('name', 'es-ES', 'Frutas', TranslationFieldStatus::Pending);

        $response = $this->patchJson(route('api.v1.admin.translations.update', $translation), [
            'value' => 'Frutas frescas',
            'status' => 'approved',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.value', 'Frutas frescas')
            ->assertJsonPath('data.status', 'approved');

        $this->assertDatabaseHas('translations', [
            'id' => $translation->id,
            'value' => 'Frutas frescas',
        ]);
    }

    // === Publish ===

    public function test_admin_can_publish_a_complete_translation(): void
    {
        $admin = $this->createAdmin();
        Sanctum::actingAs($admin);
        $category = $this->makeCategory('Fruits');
        $category->putTranslation('name', 'es-ES', 'Frutas', TranslationFieldStatus::Approved);
        $category->recalculateTranslationStatus('es-ES');

        $response = $this->postJson(route('api.v1.admin.translations.publish'), [
            'translatable_type' => 'category',
            'translatable_id' => $category->id,
            'locale' => 'es-ES',
            'publish' => true,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.status', TranslationEntityStatus::Published->value)
            ->assertJsonPath('data.locale', 'es-ES');

        $this->assertDatabaseHas('translation_statuses', [
            'translatable_type' => 'category',
            'translatable_id' => $category->id,
            'locale' => 'es-ES',
            'status' => TranslationEntityStatus::Published->value,
        ]);

        // Despublicar
        $this->postJson(route('api.v1.admin.translations.publish'), [
            'translatable_type' => 'category',
            'translatable_id' => $category->id,
            'locale' => 'es-ES',
            'publish' => false,
        ])->assertOk()
            ->assertJsonPath('data.status', TranslationEntityStatus::Incomplete->value)
            ->assertJsonPath('data.published_at', null);
    }

    public function test_publish_without_complete_translation_fails_validation(): void
    {
        $admin = $this->createAdmin();
        Sanctum::actingAs($admin);
        $category = $this->makeCategory('Fruits');

        $response = $this->postJson(route('api.v1.admin.translations.publish'), [
            'translatable_type' => 'category',
            'translatable_id' => $category->id,
            'locale' => 'es-ES',
            'publish' => true,
        ]);

        $response->assertStatus(422);
        $this->assertDatabaseMissing('translation_statuses', [
            'translatable_id' => $category->id,
            'status' => TranslationEntityStatus::Published->value,
        ]);
    }

    public function test_publish_with_invalid_entity_fails_validation(): void
    {
        $admin = $this->createAdmin();
        Sanctum::actingAs($admin);

        $this->postJson(route('api.v1.admin.translations.publish'), [
            'translatable_type' => 'category',
            'translatable_id' => Str::uuid()->toString(),
            'locale' => 'es-ES',
            'publish' => true,
        ])->assertStatus(422);
    }

    // === Destroy ===

    public function test_admin_can_delete_a_translation(): void
    {
        $admin = $this->createAdmin();
        Sanctum::actingAs($admin);
        $category = $this->makeCategory('Fruits');
        $translation = $category->putTranslation('name', 'es-ES', 'Frutas', TranslationFieldStatus::Approved);

        $this->deleteJson(route('api.v1.admin.translations.destroy', $translation))
            ->assertNoContent();

        $this->assertModelMissing($translation);
    }

    // === Consistencia con la web ===

    public function test_api_translation_is_reflected_in_the_web_panel_index(): void
    {
        $admin = $this->createAdmin();
        Sanctum::actingAs($admin);
        $category = $this->makeCategory('Fruits');

        $this->postJson(route('api.v1.admin.translations.store'), [
            'translatable_type' => 'category',
            'translatable_id' => $category->id,
            'locale' => 'es-ES',
            'field' => 'name',
            'value' => 'Frutas',
        ])->assertCreated();

        $response = $this->actingAs($admin)->get(route('admin.translations', ['locale' => 'es-ES']));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('entities.data.0.translations.es-ES.value', 'Frutas')
            ->where('entities.data.0.is_complete', true));
    }

    // === TranslationStatus: forType + forLocale vía API de estado ===

    public function test_translation_status_is_scoped_per_entity_and_locale(): void
    {
        $admin = $this->createAdmin();
        Sanctum::actingAs($admin);
        $category = $this->makeCategory('Fruits');
        $category->putTranslation('name', 'es-ES', 'Frutas', TranslationFieldStatus::Approved);
        $category->recalculateTranslationStatus('es-ES');

        $status = TranslationStatus::query()->forType('category')->forLocale('es-ES')->first();

        $this->assertNotNull($status);
        $this->assertSame($category->id, $status->translatable_id);
    }
}
