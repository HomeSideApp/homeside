<?php

namespace App\Actions\Recipes;

use App\Data\Recipes\RecipeData;
use App\Jobs\ReconcileRecipeReferences;
use App\Models\Recipe;
use App\Services\Recipes\RecipeCollectionManager;
use App\Services\Recipes\RecipeReferenceSynchronizer;
use Illuminate\Support\Facades\DB;

/**
 * Updates a recipe, replacing its nested structure with the provided data.
 */
final class UpdateRecipe
{
    public function __construct(
        private RecipeReferenceSynchronizer $referenceSynchronizer,
        private RecipeCollectionManager $collectionManager,
    ) {}

    /**
     * @param  Recipe  $recipe  The recipe to update
     * @param  RecipeData  $data  The recipe data
     * @return Recipe The Recipe value.
     */
    public function execute(Recipe $recipe, RecipeData $data): Recipe
    {
        return DB::transaction(function () use ($recipe, $data) {
            $collection = $this->collectionManager->findOrCreate($recipe->owner()->firstOrFail(), $data->collectionPath);
            $referencePathChanged = $recipe->name !== $data->name || $recipe->collection_id !== $collection?->id;
            $referenceTargetsByStepId = $recipe->steps()
                ->with('recipeReferences')
                ->get()
                ->mapWithKeys(fn ($step) => [
                    $step->id => $step->recipeReferences
                        ->pluck('referenced_recipe_id', 'order')
                        ->all(),
                ]);
            $recipe->update([
                'name' => $data->name,
                'description' => $data->description,
                'servings' => $data->servings,
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
                'author' => $data->author,
                'source_url' => $data->sourceUrl,
                'source_name' => $data->sourceName,
                'source_type' => $data->sourceType,
                'cover_image_path' => $data->coverImageUrl,
                'notes' => $data->notes,
            ]);

            // Sync sections — delete existing and recreate, build client_id → db_id map
            $recipe->sections()->delete();
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

            // Sync ingredients — delete existing and recreate
            $recipe->ingredients()->delete();
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

            // Sync steps — delete existing and recreate (including timers)
            $recipe->steps()->delete();
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

                    if (! empty($stepData->timers)) {
                        foreach ($stepData->timers as $timerData) {
                            $step->timers()->create([
                                'name' => $timerData->name,
                                'duration_seconds' => $timerData->duration_seconds,
                                'order' => $timerData->order,
                            ]);
                        }
                    }

                    $this->referenceSynchronizer->sync(
                        $recipe,
                        $step,
                        $referenceTargetsByStepId->get($stepData->id, []),
                    );
                }
            }

            // Sync cookware — delete existing and recreate
            $recipe->cookware()->delete();
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

            // Sync tags — delete existing and recreate
            $recipe->tags()->delete();
            if (! empty($data->tags)) {
                foreach ($data->tags as $tagName) {
                    $recipe->tags()->create([
                        'name' => $tagName,
                    ]);
                }
            }

            if ($referencePathChanged) {
                ReconcileRecipeReferences::dispatch($recipe->id)->afterCommit();
            }

            $recipe->refresh();

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
