<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use Database\Seeders\ProductSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductIconSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeded_icon_identifiers_reference_generated_webp_files(): void
    {
        $this->seed(ProductSeeder::class);

        $icons = Category::query()->pluck('icon')
            ->merge(Product::query()->pluck('icon'))
            ->filter()
            ->unique();

        $this->assertNotEmpty($icons);

        foreach ($icons as $icon) {
            $this->assertStringEndsWith('.webp', $icon);
            $this->assertFileExists(public_path('icons/webp/'.$icon));
        }
    }
}
