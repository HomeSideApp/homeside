<?php

namespace App\Actions\Recipes;

use App\Models\Recipe;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Creates a copy of a recipe owned by another user for the current user.
 */
final class ForkRecipe
{
    /**
     * @param  Recipe  $sourceRecipe  The recipe to fork
     * @param  User  $user  The user forking the recipe
     * @return Recipe The Recipe value.
     */
    public function execute(Recipe $sourceRecipe, User $user): Recipe
    {
        return DB::transaction(function () use ($sourceRecipe, $user) {
            // Reload with all relations
            $sourceRecipe->load(['sections', 'ingredients', 'steps.timers', 'cookware', 'tags']);

            // Create the forked recipe
            $forkedRecipe = Recipe::create([
                'name' => $sourceRecipe->name.' (copia)',
                'description' => $sourceRecipe->description,
                'servings' => $sourceRecipe->servings,
                'created_by' => $user->id,
                'owner_id' => $user->id,
                'derived_from_recipe_id' => $sourceRecipe->id,
                'yield_text' => $sourceRecipe->yield_text,
                'prep_time_seconds' => $sourceRecipe->prep_time_seconds,
                'cook_time_seconds' => $sourceRecipe->cook_time_seconds,
                'total_time_seconds' => $sourceRecipe->total_time_seconds,
                'difficulty' => $sourceRecipe->difficulty,
                'cuisine' => $sourceRecipe->cuisine,
                'locale' => $sourceRecipe->locale,
                'cooking_method' => $sourceRecipe->cooking_method,
                'recipe_category' => $sourceRecipe->recipe_category,
                'suitable_for_diet' => $sourceRecipe->suitable_for_diet,
                'author' => $sourceRecipe->author,
                'source_url' => $sourceRecipe->source_url,
                'source_name' => $sourceRecipe->source_name,
                'source_type' => $sourceRecipe->source_type,
                'notes' => $sourceRecipe->notes,
            ]);

            // Copy sections
            foreach ($sourceRecipe->sections as $section) {
                $forkedRecipe->sections()->create([
                    'name' => $section->name,
                    'order' => $section->order,
                ]);
            }

            // Copy ingredients
            foreach ($sourceRecipe->ingredients as $ingredient) {
                $forkedRecipe->ingredients()->create([
                    'name' => $ingredient->name,
                    'product_id' => $ingredient->product_id,
                    'quantity' => $ingredient->quantity,
                    'quantity_text' => $ingredient->quantity_text,
                    'unit' => $ingredient->unit,
                    'preparation' => $ingredient->preparation,
                    'notes' => $ingredient->notes,
                    'optional' => $ingredient->optional,
                    'section_id' => $ingredient->section_id,
                    'order' => $ingredient->order,
                ]);
            }

            // Copy steps with timers
            foreach ($sourceRecipe->steps as $step) {
                $forkedStep = $forkedRecipe->steps()->create([
                    'description' => $step->description,
                    'image_path' => $step->image_path,
                    'section_id' => $step->section_id,
                    'order' => $step->order,
                ]);

                foreach ($step->timers as $timer) {
                    $forkedStep->timers()->create([
                        'name' => $timer->name,
                        'duration_seconds' => $timer->duration_seconds,
                        'order' => $timer->order,
                    ]);
                }
            }

            // Copy cookware
            foreach ($sourceRecipe->cookware as $cookware) {
                $forkedRecipe->cookware()->create([
                    'name' => $cookware->name,
                    'type' => $cookware->type,
                    'quantity' => $cookware->quantity,
                    'quantity_text' => $cookware->quantity_text,
                    'unit' => $cookware->unit,
                    'section_id' => $cookware->section_id,
                    'order' => $cookware->order,
                ]);
            }

            // Copy tags
            foreach ($sourceRecipe->tags as $tag) {
                $forkedRecipe->tags()->create([
                    'name' => $tag->name,
                ]);
            }

            return $forkedRecipe->load([
                'sections',
                'ingredients.product',
                'steps.timers',
                'cookware',
                'tags',
            ]);
        });
    }
}
