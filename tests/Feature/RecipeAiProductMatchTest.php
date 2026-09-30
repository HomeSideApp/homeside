<?php

namespace Tests\Feature;

use App\Ai\Execution\RecipeNormalizer;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RecipeAiProductMatchTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    private function createProduct(string $name): Product
    {
        return Product::factory()->create([
            'name' => $name,
            'slug' => str($name.'-'.uniqid())->slug(),
            'is_active' => true,
            'created_by' => $this->user->id,
        ]);
    }

    public function test_exact_name_match_assigns_product_id(): void
    {
        $product = $this->createProduct('Tomate');
        $products = collect([$product]);

        $ingredients = [
            ['client_id' => 'ing-1', 'name' => 'Tomate', 'product_id' => null],
        ];

        $result = RecipeNormalizer::matchIngredientsToProducts($ingredients, $products);

        $this->assertSame((string) $product->id, $result[0]['product_id']);
    }

    public function test_case_insensitive_match(): void
    {
        $product = $this->createProduct('cebolla');
        $products = collect([$product]);

        $ingredients = [
            ['client_id' => 'ing-1', 'name' => 'Cebolla', 'product_id' => null],
        ];

        $result = RecipeNormalizer::matchIngredientsToProducts($ingredients, $products);

        $this->assertSame((string) $product->id, $result[0]['product_id']);
    }

    public function test_partial_match_ingredient_contains_product(): void
    {
        $product = $this->createProduct('queso');
        $products = collect([$product]);

        $ingredients = [
            ['client_id' => 'ing-1', 'name' => 'Queso parmesano', 'product_id' => null],
        ];

        $result = RecipeNormalizer::matchIngredientsToProducts($ingredients, $products);

        $this->assertSame((string) $product->id, $result[0]['product_id']);
    }

    public function test_partial_match_product_contains_ingredient(): void
    {
        $product = $this->createProduct('harina de trigo');
        $products = collect([$product]);

        $ingredients = [
            ['client_id' => 'ing-1', 'name' => 'harina', 'product_id' => null],
        ];

        $result = RecipeNormalizer::matchIngredientsToProducts($ingredients, $products);

        $this->assertSame((string) $product->id, $result[0]['product_id']);
    }

    public function test_no_match_keeps_product_id_null(): void
    {
        $product = $this->createProduct('tomate');
        $products = collect([$product]);

        $ingredients = [
            ['client_id' => 'ing-1', 'name' => 'sal', 'product_id' => null],
        ];

        $result = RecipeNormalizer::matchIngredientsToProducts($ingredients, $products);

        $this->assertNull($result[0]['product_id']);
    }

    public function test_existing_product_id_is_preserved(): void
    {
        $product = $this->createProduct('tomate');
        $products = collect([$product]);

        $ingredients = [
            ['client_id' => 'ing-1', 'name' => 'tomate', 'product_id' => (string) $product->id],
        ];

        $result = RecipeNormalizer::matchIngredientsToProducts($ingredients, $products);

        $this->assertSame((string) $product->id, $result[0]['product_id']);
    }

    public function test_invalid_product_id_is_replaced_with_match_from_ingredient_name(): void
    {
        $product = $this->createProduct('Boniato');
        $products = collect([$product]);

        $ingredients = [
            ['client_id' => 'ing-1', 'name' => 'Boniato', 'product_id' => 'Boniato'],
        ];

        $result = RecipeNormalizer::matchIngredientsToProducts($ingredients, $products);

        $this->assertSame((string) $product->id, $result[0]['product_id']);
    }

    public function test_invalid_product_id_without_name_match_is_cleared(): void
    {
        $product = $this->createProduct('Boniato');
        $products = collect([$product]);

        $ingredients = [
            ['client_id' => 'ing-1', 'name' => 'Harina', 'product_id' => 'Harina'],
        ];

        $result = RecipeNormalizer::matchIngredientsToProducts($ingredients, $products);

        $this->assertNull($result[0]['product_id']);
    }

    public function test_empty_ingredient_name_skips_matching(): void
    {
        $product = $this->createProduct('tomate');
        $products = collect([$product]);

        $ingredients = [
            ['client_id' => 'ing-1', 'name' => '', 'product_id' => null],
        ];

        $result = RecipeNormalizer::matchIngredientsToProducts($ingredients, $products);

        $this->assertNull($result[0]['product_id']);
    }

    public function test_multiple_ingredients_match_different_products(): void
    {
        $tomate = $this->createProduct('tomate');
        $cebolla = $this->createProduct('cebolla');
        $products = collect([$tomate, $cebolla]);

        $ingredients = [
            ['client_id' => 'ing-1', 'name' => 'Tomate', 'product_id' => null],
            ['client_id' => 'ing-2', 'name' => 'Cebolla', 'product_id' => null],
            ['client_id' => 'ing-3', 'name' => 'Sal', 'product_id' => null],
        ];

        $result = RecipeNormalizer::matchIngredientsToProducts($ingredients, $products);

        $this->assertSame((string) $tomate->id, $result[0]['product_id']);
        $this->assertSame((string) $cebolla->id, $result[1]['product_id']);
        $this->assertNull($result[2]['product_id']);
    }
}
