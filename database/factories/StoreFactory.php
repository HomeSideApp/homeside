<?php

namespace Database\Factories;

use App\Models\Store;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Store>
 */
class StoreFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->company();

        return [
            'name' => $name,
            'slug' => Str::slug($name),
            'icon' => fake()->randomElement(['🛒', '🏪', '🏬', '📦', null]),
            'color' => fake()->hexColor(),
        ];
    }
}
