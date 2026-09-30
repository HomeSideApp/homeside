<?php

namespace Database\Factories;

use App\Models\Recipe;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Recipe>
 */
class RecipeFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->words(3, true),
            'description' => fake()->sentence(),
            'servings' => fake()->numberBetween(1, 12),
            'created_by' => User::factory(),
            'is_public' => false,
        ];
    }

    public function public(): static
    {
        return $this->state(fn () => [
            'is_public' => true,
        ]);
    }

    public function private(): static
    {
        return $this->state(fn () => [
            'is_public' => false,
        ]);
    }
}
