<?php

namespace Database\Factories;

use App\Models\Recipe;
use App\Models\RecipeReference;
use App\Models\RecipeStep;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<RecipeReference> */
class RecipeReferenceFactory extends Factory
{
    public function definition(): array
    {
        return [
            'recipe_id' => Recipe::factory(),
            'recipe_step_id' => RecipeStep::factory(),
            'referenced_recipe_id' => Recipe::factory(),
            'path' => './'.fake()->words(2, true),
            'quantity' => null,
            'unit' => null,
            'order' => 0,
        ];
    }
}
