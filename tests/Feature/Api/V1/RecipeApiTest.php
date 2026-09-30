<?php

namespace Tests\Feature\Api\V1;

use App\Models\Household;
use App\Models\HouseholdMember;
use App\Models\HouseholdRecipe;
use App\Models\Permission;
use App\Models\PermissionGroup;
use App\Models\Recipe;
use App\Models\RecipeCollection;
use App\Models\RecipeIngredient;
use App\Models\RecipeStep;
use App\Models\Role;
use App\Models\User;
use App\Services\Recipes\Import\SafeUrlFetcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\Fixtures\FakeSafeUrlFetcher;
use Tests\TestCase;

class RecipeApiTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Household $household;

    protected function setUp(): void
    {
        parent::setUp();

        $group = PermissionGroup::create(['name' => 'Recetas']);
        Permission::create(['name' => 'view my recipes', 'route_name' => 'recipes.index', 'description' => 'Ver mis recetas', 'permission_group_id' => $group->id]);
        Permission::create(['name' => 'create recipe form', 'route_name' => 'recipes.create', 'description' => 'Formulario crear receta', 'permission_group_id' => $group->id]);
        Permission::create(['name' => 'store recipes', 'route_name' => 'recipes.store', 'description' => 'Guardar recetas', 'permission_group_id' => $group->id]);
        Permission::create(['name' => 'view recipe', 'route_name' => 'recipes.show', 'description' => 'Ver una receta', 'permission_group_id' => $group->id]);
        Permission::create(['name' => 'edit recipe form', 'route_name' => 'recipes.edit', 'description' => 'Formulario editar receta', 'permission_group_id' => $group->id]);
        Permission::create(['name' => 'update recipes', 'route_name' => 'recipes.update', 'description' => 'Actualizar recetas', 'permission_group_id' => $group->id]);
        Permission::create(['name' => 'delete recipes', 'route_name' => 'recipes.destroy', 'description' => 'Eliminar recetas', 'permission_group_id' => $group->id]);
        Permission::create(['name' => 'view household recipes', 'route_name' => 'households.recipes.index', 'description' => 'Ver recetas del hogar', 'permission_group_id' => $group->id]);
        Permission::create(['name' => 'share recipes', 'route_name' => 'households.recipes.share', 'description' => 'Compartir recetas', 'permission_group_id' => $group->id]);
        Permission::create(['name' => 'unshare recipes', 'route_name' => 'households.recipes.unshare', 'description' => 'Dejar de compartir recetas', 'permission_group_id' => $group->id]);
        Permission::create(['name' => 'add recipe to list', 'route_name' => 'households.recipes.add-to-list', 'description' => 'Añadir ingredientes a lista', 'permission_group_id' => $group->id]);
        Permission::create(['name' => 'fork recipes', 'route_name' => 'recipes.fork', 'description' => 'Forkear recetas', 'permission_group_id' => $group->id]);
        Permission::create(['name' => 'import recipes', 'route_name' => 'recipes.import', 'description' => 'Importar recetas', 'permission_group_id' => $group->id]);
        Permission::create(['name' => 'import json-ld', 'route_name' => 'recipes.import.json-ld', 'description' => 'Importar receta JSON-LD', 'permission_group_id' => $group->id]);
        Permission::create(['name' => 'import cooklang', 'route_name' => 'recipes.import.cooklang', 'description' => 'Importar receta Cooklang', 'permission_group_id' => $group->id]);
        Permission::create(['name' => 'review import', 'route_name' => 'recipes.import.review', 'description' => 'Revisar importación', 'permission_group_id' => $group->id]);
        Permission::create(['name' => 'export cooklang', 'route_name' => 'recipes.export.cooklang', 'description' => 'Exportar receta Cooklang', 'permission_group_id' => $group->id]);
        Permission::create(['name' => 'cook recipe', 'route_name' => 'recipes.cook', 'description' => 'Modo cocción', 'permission_group_id' => $group->id]);

        $role = Role::create(['name' => 'Admin', 'slug' => 'admin']);
        $role->givePermissionTo(Permission::all());

        $this->user = User::factory()->create();
        $this->user->assignRole($role);

        $this->household = Household::factory()->create(['created_by' => $this->user->id]);
        HouseholdMember::create([
            'user_id' => $this->user->id,
            'household_id' => $this->household->id,
            'role' => 'admin',
            'joined_at' => now(),
        ]);
        $this->user->update(['active_household_id' => $this->household->id]);

        Sanctum::actingAs($this->user);
    }

    // === Cookbook Personal Tests ===

    public function test_user_can_list_own_recipes(): void
    {
        Recipe::create(['name' => 'Paella', 'created_by' => $this->user->id, 'owner_id' => $this->user->id]);
        Recipe::create(['name' => 'Tortilla', 'created_by' => $this->user->id, 'owner_id' => $this->user->id]);

        $response = $this->getJson(route('api.v1.recipes.index'));

        $response->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_user_does_not_see_other_users_recipes(): void
    {
        $otherUser = User::factory()->create();

        Recipe::create(['name' => 'Mi receta', 'created_by' => $this->user->id, 'owner_id' => $this->user->id]);
        Recipe::create(['name' => 'Receta ajena', 'created_by' => $otherUser->id, 'owner_id' => $otherUser->id]);

        $response = $this->getJson(route('api.v1.recipes.index'));

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Mi receta');
    }

    public function test_user_can_create_recipe(): void
    {
        $response = $this->postJson(route('api.v1.recipes.store'), [
            'name' => 'Nueva receta',
            'description' => 'Una receta deliciosa',
            'servings' => 4,
            'ingredients' => [
                ['name' => 'Arroz', 'quantity' => 400, 'unit' => 'g'],
            ],
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.name', 'Nueva receta');

        $this->assertDatabaseHas('recipes', [
            'name' => 'Nueva receta',
            'created_by' => $this->user->id,
            'owner_id' => $this->user->id,
        ]);

        $this->assertDatabaseHas('recipe_ingredients', [
            'name' => 'Arroz',
            'quantity' => 400,
            'unit' => 'g',
        ]);
    }

    public function test_user_can_manage_recipe_collections_through_api(): void
    {
        $created = $this->postJson(route('api.v1.recipes.collections.store'), [
            'name' => 'Cenas',
        ])->assertCreated()
            ->assertJsonPath('data.name', 'Cenas');

        $collection = RecipeCollection::query()->findOrFail($created->json('data.id'));

        $this->getJson(route('api.v1.recipes.collections.index'))
            ->assertOk()
            ->assertJsonPath('data.0.id', $collection->id)
            ->assertJsonPath('data.0.recipes_count', 0);

        $this->putJson(route('api.v1.recipes.collections.update', $collection), [
            'name' => 'Cenas rápidas',
        ])->assertOk()
            ->assertJsonPath('data.name', 'Cenas rápidas');

        $this->deleteJson(route('api.v1.recipes.collections.destroy', $collection))
            ->assertNoContent();

        $this->assertModelMissing($collection);
    }

    public function test_user_cannot_manage_another_users_recipe_collection(): void
    {
        $collection = RecipeCollection::factory()->create();

        $this->putJson(route('api.v1.recipes.collections.update', $collection), [
            'name' => 'Colección ajena',
        ])->assertNotFound();

        $this->assertModelExists($collection);
    }

    public function test_ai_generation_endpoint_uses_recipe_store_permission_and_validates_prompt(): void
    {
        $this->postJson(route('api.v1.recipes.ai-generate'))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('prompt');

        $first = $this->withHeader('Idempotency-Key', 'recipe-generation-validation')
            ->postJson(route('api.v1.recipes.ai-generate'));
        $second = $this->withHeader('Idempotency-Key', 'recipe-generation-validation')
            ->postJson(route('api.v1.recipes.ai-generate'));

        $first->assertUnprocessable()->assertHeader('Idempotency-TTL', '86400');
        $second->assertUnprocessable()->assertExactJson($first->json());
    }

    public function test_user_can_view_own_recipe(): void
    {
        $recipe = Recipe::create(['name' => 'Paella', 'created_by' => $this->user->id, 'owner_id' => $this->user->id]);

        $response = $this->getJson(route('api.v1.recipes.show', $recipe));

        $response->assertOk()
            ->assertJsonPath('data.name', 'Paella');
    }

    public function test_recipe_resource_uses_api_cover_image_url(): void
    {
        $recipe = Recipe::factory()->create([
            'created_by' => $this->user->id,
            'owner_id' => $this->user->id,
            'cover_image_path' => 'recipes/cover.jpg',
        ]);

        $this->getJson(route('api.v1.recipes.show', $recipe))
            ->assertOk()
            ->assertJsonPath(
                'data.cover_image_url',
                route('api.v1.recipes.image', ['recipe' => $recipe, 'v' => $recipe->updated_at->timestamp]),
            );
    }

    public function test_user_can_download_recipe_cover_image_through_api(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('recipes/cover.jpg', 'image-content');
        $recipe = Recipe::factory()->create([
            'created_by' => $this->user->id,
            'owner_id' => $this->user->id,
            'cover_image_path' => 'recipes/cover.jpg',
        ]);

        $this->get(route('api.v1.recipes.image', $recipe))
            ->assertOk();
    }

    public function test_recipe_step_resource_uses_downloadable_api_image_url(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('recipes/step.jpg', 'image-content');
        $recipe = Recipe::factory()->create([
            'created_by' => $this->user->id,
            'owner_id' => $this->user->id,
        ]);
        $step = RecipeStep::factory()->create([
            'recipe_id' => $recipe->id,
            'image_path' => 'recipes/step.jpg',
        ]);

        $imageUrl = route('api.v1.recipes.steps.image', [
            'recipe' => $recipe,
            'step' => $step,
            'v' => $step->updated_at->timestamp,
        ]);

        $this->getJson(route('api.v1.recipes.show', $recipe))
            ->assertOk()
            ->assertJsonPath('data.steps.0.image_url', $imageUrl);

        $this->get($imageUrl)->assertOk();
    }

    public function test_user_can_export_recipe_as_cooklang_through_api(): void
    {
        $recipe = Recipe::factory()->create([
            'name' => 'Tortilla de patatas',
            'created_by' => $this->user->id,
            'owner_id' => $this->user->id,
        ]);

        $this->get(route('api.v1.recipes.export.cooklang', $recipe))
            ->assertOk()
            ->assertHeader('content-type', 'text/plain; charset=UTF-8')
            ->assertDownload('tortilla-de-patatas.cook');
    }

    public function test_user_cannot_view_other_users_recipe(): void
    {
        $otherUser = User::factory()->create();
        $recipe = Recipe::create(['name' => 'Receta ajena', 'created_by' => $otherUser->id, 'owner_id' => $otherUser->id]);

        $response = $this->getJson(route('api.v1.recipes.show', $recipe));

        $response->assertForbidden();
    }

    public function test_user_can_update_own_recipe(): void
    {
        $recipe = Recipe::create(['name' => 'Paella', 'created_by' => $this->user->id, 'owner_id' => $this->user->id]);

        $response = $this->putJson(route('api.v1.recipes.update', $recipe), [
            'name' => 'Paella Valenciana',
            'servings' => 6,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.name', 'Paella Valenciana');

        $this->assertDatabaseHas('recipes', [
            'id' => $recipe->id,
            'name' => 'Paella Valenciana',
            'servings' => 6,
        ]);
    }

    public function test_user_cannot_update_other_users_recipe(): void
    {
        $otherUser = User::factory()->create();
        $recipe = Recipe::create(['name' => 'Receta ajena', 'created_by' => $otherUser->id, 'owner_id' => $otherUser->id]);

        $response = $this->putJson(route('api.v1.recipes.update', $recipe), [
            'name' => 'Hackeada',
        ]);

        $response->assertForbidden();
    }

    public function test_user_can_delete_own_recipe(): void
    {
        $recipe = Recipe::create(['name' => 'Paella', 'created_by' => $this->user->id, 'owner_id' => $this->user->id]);

        $response = $this->deleteJson(route('api.v1.recipes.destroy', $recipe));

        $response->assertNoContent();
        $this->assertDatabaseMissing('recipes', ['id' => $recipe->id]);
    }

    public function test_user_cannot_delete_other_users_recipe(): void
    {
        $otherUser = User::factory()->create();
        $recipe = Recipe::create(['name' => 'Receta ajena', 'created_by' => $otherUser->id, 'owner_id' => $otherUser->id]);

        $response = $this->deleteJson(route('api.v1.recipes.destroy', $recipe));

        $response->assertForbidden();
        $this->assertDatabaseHas('recipes', ['id' => $recipe->id]);
    }

    public function test_validation_error_returns_422(): void
    {
        $response = $this->postJson(route('api.v1.recipes.store'), [
            'name' => '',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors('name');
    }

    public function test_unauthenticated_user_cannot_list_recipes(): void
    {
        $this->app['auth']->forgetGuards();

        $response = $this->getJson(route('api.v1.recipes.index'));

        $response->assertUnauthorized();
    }

    // === Fork Tests ===

    public function test_user_can_fork_own_recipe(): void
    {
        $recipe = Recipe::create(['name' => 'Paella', 'created_by' => $this->user->id, 'owner_id' => $this->user->id, 'servings' => 4]);
        RecipeIngredient::create(['recipe_id' => $recipe->id, 'name' => 'Arroz', 'quantity' => 400, 'unit' => 'g']);

        $response = $this->postJson(route('api.v1.recipes.fork', $recipe));

        $response->assertCreated()
            ->assertJsonPath('data.name', 'Paella (copia)');

        $this->assertDatabaseHas('recipes', [
            'name' => 'Paella (copia)',
            'owner_id' => $this->user->id,
            'derived_from_recipe_id' => $recipe->id,
        ]);

        $this->assertDatabaseHas('recipe_ingredients', [
            'name' => 'Arroz',
            'quantity' => 400,
        ]);
    }

    public function test_user_cannot_fork_a_recipe_they_cannot_view(): void
    {
        $otherUser = User::factory()->create();
        $recipe = Recipe::factory()->create([
            'created_by' => $otherUser->id,
            'owner_id' => $otherUser->id,
        ]);

        $this->postJson(route('api.v1.recipes.fork', $recipe))
            ->assertForbidden();

        $this->assertDatabaseMissing('recipes', [
            'owner_id' => $this->user->id,
            'derived_from_recipe_id' => $recipe->id,
        ]);
    }

    // === Household Sharing Tests ===

    public function test_user_can_share_recipe_with_household(): void
    {
        $recipe = Recipe::create(['name' => 'Paella', 'created_by' => $this->user->id, 'owner_id' => $this->user->id]);

        $response = $this->postJson(route('api.v1.households.recipes.share', [$this->household, $recipe]));

        $response->assertCreated();
        $this->assertDatabaseHas('household_recipes', [
            'household_id' => $this->household->id,
            'recipe_id' => $recipe->id,
            'shared_by' => $this->user->id,
        ]);
    }

    public function test_user_can_unshare_recipe_from_household(): void
    {
        $recipe = Recipe::create(['name' => 'Paella', 'created_by' => $this->user->id, 'owner_id' => $this->user->id]);
        HouseholdRecipe::create([
            'household_id' => $this->household->id,
            'recipe_id' => $recipe->id,
            'shared_by' => $this->user->id,
        ]);

        $response = $this->deleteJson(route('api.v1.households.recipes.unshare', [$this->household, $recipe]));

        $response->assertNoContent();
        $this->assertDatabaseMissing('household_recipes', [
            'household_id' => $this->household->id,
            'recipe_id' => $recipe->id,
        ]);
    }

    public function test_user_can_list_household_recipes(): void
    {
        $recipe = Recipe::create(['name' => 'Paella', 'created_by' => $this->user->id, 'owner_id' => $this->user->id]);
        HouseholdRecipe::create([
            'household_id' => $this->household->id,
            'recipe_id' => $recipe->id,
            'shared_by' => $this->user->id,
        ]);

        $response = $this->getJson(route('api.v1.households.recipes.index', $this->household));

        $response->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_non_member_cannot_access_household_recipe_endpoints(): void
    {
        $foreignHousehold = Household::factory()->create();
        $recipe = Recipe::factory()->create([
            'created_by' => $this->user->id,
            'owner_id' => $this->user->id,
        ]);

        $this->getJson(route('api.v1.households.recipes.index', $foreignHousehold))
            ->assertForbidden();

        $this->postJson(route('api.v1.households.recipes.share', [$foreignHousehold, $recipe]))
            ->assertForbidden();

        $this->assertDatabaseMissing('household_recipes', [
            'household_id' => $foreignHousehold->id,
            'recipe_id' => $recipe->id,
        ]);
    }

    public function test_recipe_with_full_structure(): void
    {
        $response = $this->postJson(route('api.v1.recipes.store'), [
            'name' => 'Receta Completa',
            'description' => 'Una receta con todo',
            'servings' => 4,
            'prep_time_seconds' => 600,
            'cook_time_seconds' => 1800,
            'total_time_seconds' => 2400,
            'difficulty' => 'medium',
            'cuisine' => 'Spanish',
            'tags' => ['paella', 'arroz'],
            'sections' => [
                ['name' => 'Ingredientes', 'order' => 0],
            ],
            'ingredients' => [
                ['name' => 'Arroz', 'quantity' => 400, 'unit' => 'g', 'section_id' => null, 'order' => 0],
                ['name' => 'Pollo', 'quantity' => 500, 'unit' => 'g', 'optional' => false, 'order' => 1],
            ],
            'steps' => [
                ['description' => 'Calentar aceite', 'order' => 0],
                ['description' => 'Añadir arroz', 'order' => 1],
            ],
            'cookware' => [
                ['name' => 'Paellera', 'type' => 'tool', 'order' => 0],
            ],
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.name', 'Receta Completa')
            ->assertJsonCount(2, 'data.ingredients')
            ->assertJsonCount(2, 'data.steps')
            ->assertJsonCount(1, 'data.cookware');
    }

    public function test_user_can_preview_json_ld_recipe_import(): void
    {
        $fetcher = new FakeSafeUrlFetcher;
        $fetcher->whenUrl('https://example.com/recipe', <<<'HTML'
            <html><head><script type="application/ld+json">
            {
                "@context": "https://schema.org",
                "@type": "Recipe",
                "name": "Tortilla API",
                "recipeIngredient": ["4 huevos", "2 patatas"],
                "recipeInstructions": ["Mezclar.", "Cocinar."]
            }
            </script></head></html>
            HTML);
        $this->app->instance(SafeUrlFetcher::class, $fetcher);

        $this->postJson(route('api.v1.recipes.import.json-ld'), [
            'url' => 'https://example.com/recipe',
        ])->assertOk()
            ->assertJsonPath('data.name', 'Tortilla API')
            ->assertJsonPath('data.source_url', 'https://example.com/recipe')
            ->assertJsonCount(2, 'data.ingredients')
            ->assertJsonCount(2, 'data.steps')
            ->assertJsonStructure(['data', 'warnings']);
    }

    public function test_json_ld_recipe_import_validates_url(): void
    {
        $this->postJson(route('api.v1.recipes.import.json-ld'), [
            'url' => 'not-a-url',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('url');
    }

    public function test_user_can_review_recipe_import(): void
    {
        $recipe = [
            'name' => 'Tortilla API',
            'ingredients' => [['name' => 'Huevos']],
            'steps' => [['description' => 'Batir']],
        ];

        $this->postJson(route('api.v1.recipes.import.review'), [
            'recipe' => $recipe,
        ])->assertOk()
            ->assertJsonPath('data', $recipe)
            ->assertJsonStructure(['data', 'warnings', 'catalog' => ['products', 'cookware']]);
    }

    public function test_recipe_import_review_rejects_incomplete_recipe(): void
    {
        $this->postJson(route('api.v1.recipes.import.review'), [
            'recipe' => ['name' => 'Sin estructura'],
        ])->assertUnprocessable()
            ->assertJsonValidationErrors([
                'recipe.ingredients',
                'recipe.steps',
            ]);
    }
}
