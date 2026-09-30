<?php

namespace App\Actions\Recipes;

use App\Data\Recipes\CreateRecipeData;
use App\Models\Recipe;
use App\Models\User;
use App\Services\Recipes\RecipeCollectionManager;
use App\Services\Recipes\RecipeReferenceSynchronizer;
use Illuminate\Support\Facades\DB;

/**
 * Creates a recipe with its nested sections, ingredients, steps, timers,
 * cookware, tags and references.
 */
final class CreateRecipe
{
    public function __construct(
        private RecipeReferenceSynchronizer $referenceSynchronizer,
        private RecipeCollectionManager $collectionManager,
    ) {}

    /**
     * @param  CreateRecipeData  $data  The recipe data
     * @param  User  $user  The user creating the recipe
     * @return Recipe The Recipe value.
     */
    public function execute(CreateRecipeData $data, User $user): Recipe
    {
        return DB::transaction(function () use ($data, $user) {
            $collection = $this->collectionManager->findOrCreate($user, $data->collectionPath);
            $recipe = Recipe::create([
                'name' => $data->name,
                'description' => $data->description,
                'servings' => $data->servings,
                'created_by' => $user->id,
                'owner_id' => $user->id,
                'collection_id' => $collection?->id,
                'yield_text' => $data->yieldText,
                'prep_time_seconds' => $data->prepTimeSeconds,
                'cook_time_seconds' => $data->cookTimeSeconds,
                'total_time_seconds' => $data->totalTimeSeconds,
                'difficulty' => $data->difficulty,
                'cuisine' => $data->cuisine,
                'locale' => $data->locale,
                'cooking_method' => $data->cookingMethod,
                'recipe_category' => $data->recipeCategory,
                'suitable_for_diet' => $data->suitableForDiet,
                'keywords' => null,
                'author' => $data->author,
                'source_url' => $data->sourceUrl,
                'source_name' => $data->sourceName,
                'source_type' => $data->sourceType,
                'cover_image_path' => $data->coverImagePath,
                'notes' => $data->notes,
            ]);

            // Sync sections — build client_id → db_id map
            $sectionMap = [];
            if (! empty($data->sections)) {
                foreach ($data->sections as $sectionData) {
                    $section = $recipe->sections()->create([
                        'name' => $sectionData->name,
                        'order' => $sectionData->order,
                    ]);
                    if (! empty($sectionData->client_id)) {
                        $sectionMap[$sectionData->client_id] = $section->id;
                    }
                }
            }

            // Sync ingredients
            if (! empty($data->ingredients)) {
                foreach ($data->ingredients as $ingredientData) {
                    $ingredientSectionId = null;
                    if (! empty($ingredientData->section_id) && isset($sectionMap[$ingredientData->section_id])) {
                        $ingredientSectionId = $sectionMap[$ingredientData->section_id];
                    }

                    $recipe->ingredients()->create([
                        'name' => $ingredientData->name,
                        'product_id' => $ingredientData->product_id,
                        'quantity' => $ingredientData->quantity,
                        'quantity_text' => $ingredientData->quantity_text,
                        'unit' => $ingredientData->unit,
                        'preparation' => $ingredientData->preparation,
                        'notes' => $ingredientData->notes,
                        'optional' => $ingredientData->optional,
                        'section_id' => $ingredientSectionId,
                        'order' => $ingredientData->order,
                    ]);
                }
            }

            // Sync steps with timers
            if (! empty($data->steps)) {
                foreach ($data->steps as $stepData) {
                    $stepSectionId = null;
                    if (! empty($stepData->section_id) && isset($sectionMap[$stepData->section_id])) {
                        $stepSectionId = $sectionMap[$stepData->section_id];
                    }

                    $step = $recipe->steps()->create([
                        'description' => $stepData->description,
                        'image_path' => $stepData->image_path,
                        'section_id' => $stepSectionId,
                        'order' => $stepData->order,
                    ]);

                    // Sync step timers
                    if (! empty($stepData->timers)) {
                        foreach ($stepData->timers as $timerData) {
                            $step->timers()->create([
                                'name' => $timerData->name,
                                'duration_seconds' => $timerData->duration_seconds,
                                'order' => $timerData->order,
                            ]);
                        }
                    }

                    $this->referenceSynchronizer->sync($recipe, $step, $stepData->reference_recipe_ids);
                }
            }

            // Sync cookware
            if (! empty($data->cookware)) {
                foreach ($data->cookware as $cookwareData) {
                    $cookwareSectionId = null;
                    if (! empty($cookwareData->section_id) && isset($sectionMap[$cookwareData->section_id])) {
                        $cookwareSectionId = $sectionMap[$cookwareData->section_id];
                    }

                    $recipe->cookware()->create([
                        'name' => $cookwareData->name,
                        'type' => $cookwareData->type,
                        'quantity' => $cookwareData->quantity,
                        'quantity_text' => $cookwareData->quantity_text,
                        'unit' => $cookwareData->unit,
                        'section_id' => $cookwareSectionId,
                        'order' => $cookwareData->order,
                    ]);
                }
            }

            // Sync tags
            if (! empty($data->tags)) {
                foreach ($data->tags as $tagName) {
                    $recipe->tags()->create([
                        'name' => $tagName,
                    ]);
                }
            }

            return $recipe->load([
                'sections',
                'ingredients.product',
                'steps.timers',
                'steps.recipeReferences.referencedRecipe',
                'cookware',
                'tags',
                'collection',
            ]);
        });
    }
}
