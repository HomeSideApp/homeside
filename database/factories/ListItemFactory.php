<?php

namespace Database\Factories;

use App\Models\ListItem;
use App\Models\Product;
use App\Models\ShoppingList;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ListItem>
 */
class ListItemFactory extends Factory
{
    public function definition(): array
    {
        return [
            'list_id' => ShoppingList::factory(),
            'product_id' => Product::factory(),
            'custom_name' => null,
            'quantity' => fake()->randomFloat(2, 1, 10),
            'unit' => fake()->randomElement(['kg', 'g', 'l', 'ml', 'uds', null]),
            'is_checked' => false,
            'notes' => fake()->optional()->words(2, true),
            'store_id' => null,
            'image_url' => null,
            'icon' => null,
            'category_id' => null,
        ];
    }

    public function checked(): static
    {
        return $this->state(fn () => [
            'is_checked' => true,
        ]);
    }

    public function unchecked(): static
    {
        return $this->state(fn () => [
            'is_checked' => false,
        ]);
    }
}
