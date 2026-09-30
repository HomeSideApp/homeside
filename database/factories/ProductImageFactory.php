<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\ProductImage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductImage>
 */
class ProductImageFactory extends Factory
{
    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'image_url' => '/storage/products/'.fake()->uuid().'.png',
            'is_selected' => false,
            'is_ai_generated' => true,
            'prompt' => fake()->sentence(),
        ];
    }

    public function selected(): static
    {
        return $this->state(fn () => [
            'is_selected' => true,
        ]);
    }
}
