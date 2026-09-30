<?php

namespace Tests\Feature;

use App\Actions\Recipes\AddToShoppingList;
use App\Data\Recipes\AddToListData;
use App\Jobs\ReconcileRecipeReferences;
use App\Models\Recipe;
use App\Models\ShoppingList;
use App\Models\User;
use App\Services\Recipes\RecipeCollectionManager;
use App\Services\Recipes\RecipeReferenceSynchronizer;
use App\Services\Recipes\ReconcileRecipeReferencePaths;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class RecipeDependencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_reference_paths_are_reconciled_from_the_persistent_uuid_after_a_rename(): void
    {
        $user = User::factory()->create();
        $sauce = Recipe::factory()->for($user, 'creator')->create([
            'owner_id' => $user->id,
            'name' => 'Salsa Verde',
        ]);
        $peppers = Recipe::factory()->for($user, 'creator')->create([
            'owner_id' => $user->id,
            'name' => 'Pimientos',
        ]);
        $step = $peppers->steps()->create([
            'description' => 'Servir con @./Salsa Verde{}.',
            'order' => 1,
        ]);
        $reference = $step->recipeReferences()->create([
            'recipe_id' => $peppers->id,
            'referenced_recipe_id' => $sauce->id,
            'path' => './Salsa Verde',
            'order' => 0,
        ]);

        $sauce->update(['name' => 'Salsa Verde Picante']);
        app(ReconcileRecipeReferencePaths::class)->execute($sauce->id);

        $this->assertSame('Servir con @./Salsa Verde Picante{}.', $step->refresh()->description);
        $reconciledReference = $step->recipeReferences()->firstOrFail();
        $this->assertSame($sauce->id, $reconciledReference->referenced_recipe_id);
        $this->assertSame('./Salsa Verde Picante', $reconciledReference->path);
    }

    public function test_scheduled_reconciliation_command_dispatches_the_unique_job(): void
    {
        Queue::fake();

        $this->artisan('recipes:reconcile-references')->assertSuccessful();

        Queue::assertPushed(
            ReconcileRecipeReferences::class,
            fn (ReconcileRecipeReferences $job) => $job->referencedRecipeId === null,
        );
    }

    public function test_user_can_manage_recipe_collections_and_recipes_are_preserved_when_deleted(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $this->post(route('recipes.collections.store'), [
            'name' => 'Salsas',
            'parent_id' => null,
        ])->assertRedirect();

        $collection = $user->recipeCollections()->firstOrFail();
        $recipe = Recipe::factory()->for($user, 'creator')->create([
            'owner_id' => $user->id,
            'collection_id' => $collection->id,
        ]);

        $this->get(route('recipes.collections.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('recipes/collections/Index')
                ->has('collections', 1)
                ->where('collections.0.recipes.0.id', $recipe->id));

        $this->put(route('recipes.collections.update', $collection), ['name' => 'Aderezos'])
            ->assertRedirect();
        $this->assertSame('Aderezos', $collection->refresh()->path);

        $this->delete(route('recipes.collections.destroy', $collection))
            ->assertRedirect();

        $this->assertModelExists($recipe);
        $this->assertNull($recipe->refresh()->collection_id);
    }

    public function test_cooklang_path_is_resolved_to_a_persistent_recipe_reference(): void
    {
        $user = User::factory()->create();
        $collection = app(RecipeCollectionManager::class)->findOrCreate($user, 'Sauces/Green');
        $sauce = Recipe::factory()->for($user, 'creator')->create([
            'owner_id' => $user->id,
            'collection_id' => $collection->id,
            'name' => 'Salsa Verde',
        ]);
        $peppers = Recipe::factory()->for($user, 'creator')->create([
            'owner_id' => $user->id,
            'name' => 'Pimientos rellenos',
        ]);
        $step = $peppers->steps()->create([
            'description' => 'Servir con @./Sauces/Green/Salsa Verde{}.',
            'order' => 1,
        ]);

        app(RecipeReferenceSynchronizer::class)->sync($peppers, $step);

        $this->assertTrue($step->recipeReferences()->firstOrFail()->referencedRecipe->is($sauce));
        $this->assertSame('Sauces/Green', $collection->path);
    }

    public function test_referenced_recipe_ingredients_are_added_recursively_with_scaling(): void
    {
        $user = User::factory()->create();
        $sauce = Recipe::factory()->for($user, 'creator')->create([
            'owner_id' => $user->id,
            'name' => 'Salsa Verde',
            'servings' => 4,
        ]);
        $sauce->ingredients()->create(['name' => 'Perejil', 'quantity' => 20, 'unit' => 'g']);

        $peppers = Recipe::factory()->for($user, 'creator')->create([
            'owner_id' => $user->id,
            'name' => 'Pimientos rellenos',
            'servings' => 2,
        ]);
        $peppers->ingredients()->create(['name' => 'Pimientos', 'quantity' => 2, 'unit' => 'unidad']);
        $step = $peppers->steps()->create(['description' => 'Servir con @./Salsas/Salsa Verde{4%servings}.', 'order' => 1]);
        $step->recipeReferences()->create([
            'recipe_id' => $peppers->id,
            'referenced_recipe_id' => $sauce->id,
            'path' => './Salsas/Salsa Verde',
            'quantity' => 4,
            'unit' => 'servings',
        ]);

        $list = ShoppingList::factory()->for($user, 'creator')->create();

        $items = app(AddToShoppingList::class)->execute(
            $peppers,
            $list,
            new AddToListData(target_servings: 4),
            $user,
        );

        $this->assertCount(2, $items);
        $this->assertSame(4.0, (float) $items->firstWhere('custom_name', 'Pimientos')->quantity);
        $this->assertSame(40.0, (float) $items->firstWhere('custom_name', 'Perejil')->quantity);
    }

    public function test_recursive_references_do_not_loop_forever(): void
    {
        $user = User::factory()->create();
        $first = Recipe::factory()->for($user, 'creator')->create(['owner_id' => $user->id, 'name' => 'Primera', 'servings' => 1]);
        $second = Recipe::factory()->for($user, 'creator')->create(['owner_id' => $user->id, 'name' => 'Segunda', 'servings' => 1]);
        $first->ingredients()->create(['name' => 'A', 'quantity' => 1]);
        $second->ingredients()->create(['name' => 'B', 'quantity' => 1]);

        $firstStep = $first->steps()->create(['description' => '@./Segunda{}', 'order' => 1]);
        $secondStep = $second->steps()->create(['description' => '@./Primera{}', 'order' => 1]);
        $firstStep->recipeReferences()->create(['recipe_id' => $first->id, 'referenced_recipe_id' => $second->id, 'path' => './Segunda']);
        $secondStep->recipeReferences()->create(['recipe_id' => $second->id, 'referenced_recipe_id' => $first->id, 'path' => './Primera']);

        $list = ShoppingList::factory()->for($user, 'creator')->create();
        $items = app(AddToShoppingList::class)->execute($first, $list, new AddToListData(1), $user);

        $this->assertCount(2, $items);
    }
}
