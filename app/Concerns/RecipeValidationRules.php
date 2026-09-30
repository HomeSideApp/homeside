<?php

namespace App\Concerns;

/**
 * Shared validation rules for recipe requests.
 */
trait RecipeValidationRules
{
    /**
     * Get the full set of validation rules for a recipe,
     * including sections, ingredients, steps, timers and cookware.
     *
     * @return array<string, mixed>
     */
    protected function recipeRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'servings' => ['nullable', 'integer', 'min:1'],
            'yield_text' => ['nullable', 'string', 'max:255'],
            'prep_time_seconds' => ['nullable', 'integer', 'min:0'],
            'cook_time_seconds' => ['nullable', 'integer', 'min:0'],
            'total_time_seconds' => ['nullable', 'integer', 'min:0'],
            'difficulty' => ['nullable', 'string', 'max:50'],
            'cuisine' => ['nullable', 'string', 'max:100'],
            'locale' => ['nullable', 'string', 'max:10'],
            'cooking_method' => ['nullable', 'string', 'max:100'],
            'recipe_category' => ['nullable', 'string', 'max:100'],
            'collection_path' => ['nullable', 'string', 'max:500', 'not_regex:/[\\\\:*?"<>|]/'],
            'suitable_for_diet' => ['nullable', 'array'],
            'suitable_for_diet.*' => ['string', 'max:100'],
            'keywords' => ['nullable', 'array'],
            'keywords.*' => ['string', 'max:100'],
            'author' => ['nullable', 'string', 'max:255'],
            'source_url' => ['nullable', 'url', 'max:2048'],
            'source_name' => ['nullable', 'string', 'max:255'],
            'source_type' => ['nullable', 'string', 'max:50'],
            'cover_image_path' => ['nullable', 'string', 'max:500'],
            'notes' => ['nullable', 'string'],

            // Tags
            'tags' => ['nullable', 'array'],
            'tags.*' => ['string', 'max:100'],

            // Sections
            'sections' => ['nullable', 'array'],
            'sections.*.id' => ['nullable', 'string', 'max:36'],
            'sections.*.client_id' => ['nullable', 'string', 'max:36'],
            'sections.*.name' => ['required_with:sections', 'string', 'max:255'],
            'sections.*.order' => ['nullable', 'integer', 'min:0'],

            // Ingredients
            'ingredients' => ['nullable', 'array'],
            'ingredients.*.id' => ['nullable', 'string', 'max:36'],
            'ingredients.*.client_id' => ['nullable', 'string', 'max:36'],
            'ingredients.*.name' => ['required_with:ingredients', 'string', 'max:255'],
            'ingredients.*.product_id' => ['nullable', 'exists:products,id'],
            'ingredients.*.quantity' => ['nullable', 'numeric', 'min:0'],
            'ingredients.*.quantity_text' => ['nullable', 'string', 'max:100'],
            'ingredients.*.unit' => ['nullable', 'string', 'max:50'],
            'ingredients.*.preparation' => ['nullable', 'string', 'max:255'],
            'ingredients.*.notes' => ['nullable', 'string', 'max:255'],
            'ingredients.*.optional' => ['nullable', 'boolean'],
            'ingredients.*.section_id' => ['nullable', 'string', 'max:36'],
            'ingredients.*.order' => ['nullable', 'integer', 'min:0'],

            // Steps
            'steps' => ['nullable', 'array'],
            'steps.*.id' => ['nullable', 'string', 'max:36'],
            'steps.*.client_id' => ['nullable', 'string', 'max:36'],
            'steps.*.description' => ['required_with:steps', 'string'],
            'steps.*.image_path' => ['nullable', 'string', 'max:500'],
            'steps.*.section_id' => ['nullable', 'string', 'max:36'],
            'steps.*.order' => ['nullable', 'integer', 'min:0'],
            'steps.*.ingredients' => ['nullable', 'array'],
            'steps.*.ingredients.*' => ['string', 'max:36'],
            'steps.*.cookware' => ['nullable', 'array'],
            'steps.*.cookware.*' => ['string', 'max:36'],
            'steps.*.reference_recipe_ids' => ['nullable', 'array'],
            'steps.*.reference_recipe_ids.*' => ['string', 'max:36'],

            // Step timers
            'steps.*.timers' => ['nullable', 'array'],
            'steps.*.timers.*.id' => ['nullable', 'string', 'max:36'],
            'steps.*.timers.*.client_id' => ['nullable', 'string', 'max:36'],
            'steps.*.timers.*.name' => ['nullable', 'string', 'max:255'],
            'steps.*.timers.*.duration_seconds' => ['nullable', 'integer', 'min:0'],
            'steps.*.timers.*.order' => ['nullable', 'integer', 'min:0'],

            // Cookware
            'cookware' => ['nullable', 'array'],
            'cookware.*.id' => ['nullable', 'string', 'max:36'],
            'cookware.*.client_id' => ['nullable', 'string', 'max:36'],
            'cookware.*.name' => ['required_with:cookware', 'string', 'max:255'],
            'cookware.*.type' => ['nullable', 'string', 'max:50'],
            'cookware.*.quantity' => ['nullable', 'integer', 'min:0'],
            'cookware.*.quantity_text' => ['nullable', 'string', 'max:100'],
            'cookware.*.unit' => ['nullable', 'string', 'max:50'],
            'cookware.*.section_id' => ['nullable', 'string', 'max:36'],
            'cookware.*.order' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
