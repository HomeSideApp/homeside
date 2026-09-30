<?php

namespace Database\Factories;

use App\Models\ShoppingList;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ShoppingList>
 */
class ShoppingListFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->words(2, true),
            'created_by' => User::factory(),
        ];
    }
}
