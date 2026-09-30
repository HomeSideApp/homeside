<?php

namespace Database\Factories;

use App\Models\Category;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Category>
 */
class CategoryFactory extends Factory
{
    public function definition(): array
    {
        /** @var string $name */
        $name = fake()->unique()->words(asText: true);

        return [
            'name' => $name,
            'slug' => Str::slug($name),
            'icon' => fake()->randomElement(['🍎', '🥖', '🥛', '🧀', '🥩', null]),
            'color' => fake()->hexColor(),
            'is_active' => true,
            'sort_order' => fake()->numberBetween(0, 100),
        ];
    }
}
