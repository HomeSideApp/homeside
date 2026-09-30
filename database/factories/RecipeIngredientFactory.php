<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\Recipe;
use App\Models\RecipeIngredient;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RecipeIngredient>
 */
class RecipeIngredientFactory extends Factory
{
    public function definition(): array
    {
        return [
            'recipe_id' => Recipe::factory(),
            'product_id' => Product::factory(),
            'quantity' => fake()->randomFloat(2, 0.1, 10),
            'unit' => fake()->randomElement(['kg', 'g', 'l', 'ml', 'uds', 'docena', null]),
            'notes' => fake()->optional()->words(3, true),
        ];
    }
}
