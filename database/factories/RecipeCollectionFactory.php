<?php

namespace Database\Factories;

use App\Models\RecipeCollection;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<RecipeCollection> */
class RecipeCollectionFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->unique()->word();

        return [
            'owner_id' => User::factory(),
            'parent_id' => null,
            'name' => $name,
            'slug' => Str::slug($name),
            'path' => $name,
        ];
    }
}
