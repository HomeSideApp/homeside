<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->words(2, true);

        return [
            'name' => ucfirst($name),
            'slug' => Str::slug($name),
            'category_id' => null,
            'icon' => null,
            'created_by' => User::factory(),
        ];
    }

    public function pending(): static
    {
        return $this->state(fn () => [
            'needs_image' => true,
            'is_approved' => false,
        ]);
    }

    public function approved(): static
    {
        return $this->state(fn () => [
            'is_approved' => true,
        ]);
    }
}
