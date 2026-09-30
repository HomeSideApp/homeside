<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\Recipes\CreateRecipe;
use App\Data\Recipes\CreateRecipeData;
use App\Models\Household;
use App\Models\HouseholdRecipe;
use App\Models\Recipe;
use App\Models\User;
use App\Services\Recipes\RecipeCollectionManager;
use App\Services\Recipes\RecipeReferenceCatalog;
use App\Services\Recipes\RecipeReferenceTargetResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Covers the flow that lets the AI recipe generator link an existing recipe
 * (a bolognese sauce) instead of re-explaining it inside the new recipe.
 */
class RecipeAiReferenceLinkTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
    }

    // --- RecipeReferenceCatalog (prompt context) ---

    #[Test]
    public function the_catalog_lists_the_id_and_the_cooklang_path_of_an_owned_recipe(): void
    {
        $collection = app(RecipeCollectionManager::class)->findOrCreate($this->user, 'Salsas');
        $sauce = Recipe::factory()->for($this->user, 'creator')->create([
            'owner_id' => $this->user->id,
            'collection_id' => $collection->id,
            'name' => 'Boloñesa',
            'servings' => 4,
        ]);

        $catalog = app(RecipeReferenceCatalog::class)->build($this->user);

        $this->assertNotNull($catalog);
        $this->assertStringContainsString('Boloñesa', $catalog);
        $this->assertStringContainsString('./Salsas/Boloñesa', $catalog);
        $this->assertStringContainsString($sauce->id, $catalog);
        $this->assertStringContainsString('4 raciones', $catalog);
    }

    #[Test]
    public function the_catalog_is_null_when_the_user_owns_no_recipes(): void
    {
        $this->assertNull(app(RecipeReferenceCatalog::class)->build($this->user));
    }

    #[Test]
    public function the_catalog_does_not_leak_recipes_of_other_users(): void
    {
        $otherUser = User::factory()->create();
        Recipe::factory()->for($otherUser, 'creator')->create([
            'owner_id' => $otherUser->id,
            'name' => 'Boloñesa secreta',
        ]);

        $this->assertNull(app(RecipeReferenceCatalog::class)->build($this->user));
    }

    // --- RecipeReferenceTargetResolver ---

    #[Test]
    public function the_resolver_maps_a_reference_path_to_the_owned_recipe_id(): void
    {
        $sauce = Recipe::factory()->for($this->user, 'creator')->create([
            'owner_id' => $this->user->id,
            'name' => 'Boloñesa',
        ]);

        $resolver = app(RecipeReferenceTargetResolver::class);
        $idByPath = $resolver->idByPath([
            ['path' => './Boloñesa', 'recipe_id' => $sauce->id],
        ], $this->user);

        $this->assertSame([$sauce->id], array_values($idByPath));
    }

    #[Test]
    public function the_resolver_falls_back_to_the_path_when_the_model_omits_the_id(): void
    {
        $sauce = Recipe::factory()->for($this->user, 'creator')->create([
            'owner_id' => $this->user->id,
            'name' => 'Boloñesa',
        ]);

        $resolver = app(RecipeReferenceTargetResolver::class);
        $idByPath = $resolver->idByPath([
            ['path' => './Boloñesa', 'recipe_id' => null],
        ], $this->user);

        $this->assertSame([$sauce->id], array_values($idByPath));
    }

    #[Test]
    public function the_resolver_ignores_a_recipe_id_that_does_not_match_its_path(): void
    {
        $sauce = Recipe::factory()->for($this->user, 'creator')->create([
            'owner_id' => $this->user->id,
            'name' => 'Boloñesa',
        ]);
        $other = Recipe::factory()->for($this->user, 'creator')->create([
            'owner_id' => $this->user->id,
            'name' => 'Carbonara',
        ]);

        $resolver = app(RecipeReferenceTargetResolver::class);
        $idByPath = $resolver->idByPath([
            ['path' => './Boloñesa', 'recipe_id' => $other->id],
        ], $this->user);

        // The path wins: the mismatched id is discarded.
        $this->assertSame([$sauce->id], array_values($idByPath));
    }

    #[Test]
    public function the_resolver_discards_a_recipe_id_the_user_does_not_own(): void
    {
        $otherUser = User::factory()->create();
        $foreign = Recipe::factory()->for($otherUser, 'creator')->create([
            'owner_id' => $otherUser->id,
            'name' => 'Boloñesa ajena',
        ]);

        $resolver = app(RecipeReferenceTargetResolver::class);
        $idByPath = $resolver->idByPath([
            ['path' => './Boloñesa ajena', 'recipe_id' => $foreign->id],
        ], $this->user);

        $this->assertSame([], $idByPath);
    }

    #[Test]
    public function the_resolver_discards_a_recipe_shared_with_a_household_but_not_owned(): void
    {
        $household = Household::factory()->create(['created_by' => $this->user->id]);
        $household->members()->create([
            'user_id' => $this->user->id,
            'role' => 'member',
            'joined_at' => now(),
        ]);

        $otherUser = User::factory()->create();
        $shared = Recipe::factory()->for($otherUser, 'creator')->create([
            'owner_id' => $otherUser->id,
            'name' => 'Boloñesa compartida',
        ]);
        HouseholdRecipe::create([
            'household_id' => $household->id,
            'recipe_id' => $shared->id,
            'shared_by' => $otherUser->id,
        ]);

        $resolver = app(RecipeReferenceTargetResolver::class);
        $idByPath = $resolver->idByPath([
            ['path' => './Boloñesa compartida', 'recipe_id' => $shared->id],
        ], $this->user);

        $this->assertSame([], $idByPath);
    }

    #[Test]
    public function the_resolver_aligns_targets_with_the_position_of_each_reference(): void
    {
        $sauce = Recipe::factory()->for($this->user, 'creator')->create([
            'owner_id' => $this->user->id,
            'name' => 'Boloñesa',
        ]);

        $resolver = app(RecipeReferenceTargetResolver::class);
        $targets = $resolver->targetsForDescription(
            'Añadir @./Desconocida{} y luego @./Boloñesa{4%raciones}.',
            ['boloñesa' => $sauce->id],
        );

        // The first reference does not resolve, so its slot stays empty and the
        // resolvable one keeps its own position.
        $this->assertSame([1 => $sauce->id], $targets);
    }

    #[Test]
    public function the_resolver_uses_the_agent_reference_ids_when_linking_steps(): void
    {
        $sauce = Recipe::factory()->for($this->user, 'creator')->create([
            'owner_id' => $this->user->id,
            'name' => 'Boloñesa',
        ]);

        $resolver = app(RecipeReferenceTargetResolver::class);
        $recipe = $resolver->applyToSteps([
            'recipe_references' => [['path' => './Boloñesa', 'recipe_id' => $sauce->id]],
            'steps' => [
                [
                    'client_id' => 'step-1',
                    'description' => 'Mezclar @espaguetis{400%g} con @./Boloñesa{4%raciones}.',
                ],
            ],
        ], $this->user);

        // Only the recipe reference occupies position 0: the ingredient in the
        // same description is not a recipe reference.
        $this->assertSame([0 => $sauce->id], $recipe['steps'][0]['reference_recipe_ids']);
    }

    /**
     * The model does not always fill the `recipe_references` array; the paths
     * embedded in the step descriptions alone must still resolve.
     */
    #[Test]
    public function the_resolver_resolves_the_paths_embedded_in_the_step_without_the_reference_array(): void
    {
        $sauce = Recipe::factory()->for($this->user, 'creator')->create([
            'owner_id' => $this->user->id,
            'name' => 'Boloñesa',
        ]);

        $resolver = app(RecipeReferenceTargetResolver::class);
        $recipe = $resolver->applyToSteps([
            'steps' => [
                [
                    'client_id' => 'step-1',
                    'description' => 'Mezclar @espaguetis{400%g} con @./Boloñesa{4%raciones}.',
                ],
            ],
        ], $this->user);

        $this->assertSame([0 => $sauce->id], $recipe['steps'][0]['reference_recipe_ids']);
    }

    // --- Persistence ---

    #[Test]
    public function creating_a_recipe_persists_the_linked_reference_without_a_name_match(): void
    {
        $sauce = Recipe::factory()->for($this->user, 'creator')->create([
            'owner_id' => $this->user->id,
            'name' => 'Boloñesa',
        ]);

        $data = CreateRecipeData::fromArray([
            'name' => 'Espaguetis con salsa boloñesa',
            'ingredients' => [['client_id' => 'ing-1', 'name' => 'espaguetis', 'quantity' => 400, 'unit' => 'g']],
            'steps' => [[
                'client_id' => 'step-1',
                'description' => 'Mezclar @espaguetis{400%g} con @./Salsa que no coincide{4%raciones}.',
                'ingredients' => ['ing-1'],
                'reference_recipe_ids' => [0 => $sauce->id],
            ]],
        ]);

        $recipe = app(CreateRecipe::class)->execute($data, $this->user);

        $reference = $recipe->steps()->firstOrFail()->recipeReferences()->firstOrFail();

        $this->assertSame($sauce->id, $reference->referenced_recipe_id);
    }

    #[Test]
    public function creating_a_recipe_sets_a_null_target_for_an_unresolvable_reference(): void
    {
        $data = CreateRecipeData::fromArray([
            'name' => 'Espaguetis solos',
            'ingredients' => [['client_id' => 'ing-1', 'name' => 'espaguetis', 'quantity' => 400, 'unit' => 'g']],
            'steps' => [[
                'client_id' => 'step-1',
                'description' => 'Cocer @espaguetis{400%g} con @./Receta inexistente{}.',
                'ingredients' => ['ing-1'],
            ]],
        ]);

        $recipe = app(CreateRecipe::class)->execute($data, $this->user);

        $this->assertSame(0, $recipe->steps()->firstOrFail()->recipeReferences()->count());
    }
}
