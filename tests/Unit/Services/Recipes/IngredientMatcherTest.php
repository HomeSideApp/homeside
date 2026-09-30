<?php

namespace Tests\Unit\Services\Recipes;

use App\Models\Product;
use App\Services\Recipes\IngredientMatcher;
use PHPUnit\Framework\TestCase;

class IngredientMatcherTest extends TestCase
{
    private IngredientMatcher $matcher;

    protected function setUp(): void
    {
        parent::setUp();

        $this->matcher = new IngredientMatcher;
    }

    private function makeProduct(string $id, string $name): Product
    {
        $product = new Product;
        $product->id = $id;
        $product->name = $name;

        return $product;
    }

    public function test_exact_match_returns_matched_status(): void
    {
        $products = collect([
            $this->makeProduct('1', 'Tomate'),
            $this->makeProduct('2', 'Lechuga'),
        ]);

        $result = $this->matcher->match('Tomate', $products);

        $this->assertSame(IngredientMatcher::MATCHED, $result['status']);
        $this->assertSame('1', $result['product_id']);
        $this->assertEmpty($result['suggestions']);
    }

    public function test_exact_match_is_case_insensitive(): void
    {
        $products = collect([
            $this->makeProduct('1', 'Tomate'),
        ]);

        $result = $this->matcher->match('tomate', $products);

        $this->assertSame(IngredientMatcher::MATCHED, $result['status']);
        $this->assertSame('1', $result['product_id']);
    }

    public function test_fuzzy_match_returns_suggested_status(): void
    {
        $products = collect([
            $this->makeProduct('1', 'Tomate cherry'),
            $this->makeProduct('2', 'Lechuga'),
        ]);

        $result = $this->matcher->match('tomates cherry', $products);

        $this->assertSame(IngredientMatcher::SUGGESTED, $result['status']);
        $this->assertNull($result['product_id']);
        $this->assertNotEmpty($result['suggestions']);
        $this->assertSame('1', $result['suggestions'][0]['id']);
    }

    public function test_unmatched_returns_unmatched_status(): void
    {
        $products = collect([
            $this->makeProduct('1', 'Lechuga'),
        ]);

        $result = $this->matcher->match('sazonador especial', $products);

        $this->assertSame(IngredientMatcher::UNMATCHED, $result['status']);
        $this->assertNull($result['product_id']);
        $this->assertEmpty($result['suggestions']);
    }

    public function test_empty_name_returns_unmatched(): void
    {
        $products = collect([
            $this->makeProduct('1', 'Tomate'),
        ]);

        $result = $this->matcher->match('', $products);

        $this->assertSame(IngredientMatcher::UNMATCHED, $result['status']);
        $this->assertNull($result['product_id']);
    }

    public function test_empty_products_returns_unmatched(): void
    {
        $products = collect();

        $result = $this->matcher->match('Tomate', $products);

        $this->assertSame(IngredientMatcher::UNMATCHED, $result['status']);
        $this->assertNull($result['product_id']);
    }

    public function test_suggestions_are_limited_to_5(): void
    {
        $products = collect([
            $this->makeProduct('1', 'Tomate red'),
            $this->makeProduct('2', 'Tomate green'),
            $this->makeProduct('3', 'Tomate yellow'),
            $this->makeProduct('4', 'Tomate orange'),
            $this->makeProduct('5', 'Tomate purple'),
            $this->makeProduct('6', 'Tomate pink'),
        ]);

        $result = $this->matcher->match('Tomate', $products);

        $this->assertLessThanOrEqual(5, count($result['suggestions']));
    }

    public function test_exact_match_returns_product_name(): void
    {
        $products = collect([
            $this->makeProduct('1', 'Tomate'),
        ]);

        $result = $this->matcher->match('Tomate', $products);

        $this->assertSame('Tomate', $result['product_name']);
    }

    public function test_containment_ingredient_contains_product_scores_full(): void
    {
        $products = collect([
            $this->makeProduct('1', 'queso'),
        ]);

        $result = $this->matcher->match('Queso parmesano', $products);

        $this->assertSame(IngredientMatcher::SUGGESTED, $result['status']);
        $this->assertSame(1.0, $result['suggestions'][0]['score']);
    }

    public function test_containment_product_contains_ingredient_scores_full(): void
    {
        $products = collect([
            $this->makeProduct('1', 'harina de trigo'),
        ]);

        $result = $this->matcher->match('harina', $products);

        $this->assertSame(IngredientMatcher::SUGGESTED, $result['status']);
        $this->assertSame(1.0, $result['suggestions'][0]['score']);
    }

    public function test_match_many_returns_candidates_above_threshold(): void
    {
        $products = collect([
            $this->makeProduct('1', 'Tomate frito'),
            $this->makeProduct('2', 'Cebolla'),
        ]);

        $candidates = $this->matcher->matchMany(['tomate', 'Cebolla', 'sal'], $products);

        $this->assertCount(2, $candidates);
        $this->assertSame('tomate', $candidates[0]['ingredient']);
        $this->assertSame('1', $candidates[0]['product_id']);
        $this->assertSame(1.0, $candidates[0]['score']);
        $this->assertSame('Cebolla', $candidates[1]['ingredient']);
        $this->assertSame('2', $candidates[1]['product_id']);
    }

    public function test_match_many_skips_candidates_below_threshold(): void
    {
        $products = collect([
            $this->makeProduct('1', 'Tomate frito'),
        ]);

        $candidates = $this->matcher->matchMany(['Tomate frito', 'sal'], $products, 0.5);

        $this->assertCount(1, $candidates);
        $this->assertSame('Tomate frito', $candidates[0]['ingredient']);
        $this->assertSame('1', $candidates[0]['product_id']);
    }

    public function test_match_many_uses_top_suggestion_as_product_id(): void
    {
        $products = collect([
            $this->makeProduct('1', 'Tomate verde frito'),
        ]);

        $candidates = $this->matcher->matchMany(['tomate frito'], $products);

        $this->assertCount(1, $candidates);
        $this->assertSame(IngredientMatcher::SUGGESTED, $candidates[0]['status']);
        $this->assertSame('1', $candidates[0]['product_id']);
        $this->assertSame('Tomate verde frito', $candidates[0]['product_name']);
    }

    public function test_match_many_ignores_empty_names(): void
    {
        $products = collect([
            $this->makeProduct('1', 'Tomate'),
        ]);

        $candidates = $this->matcher->matchMany(['', '   ', 'Tomate'], $products);

        $this->assertCount(1, $candidates);
        $this->assertSame('Tomate', $candidates[0]['ingredient']);
    }

    public function test_implements_interface_contract(): void
    {
        $products = collect([
            $this->makeProduct('1', 'Test'),
        ]);

        $result = $this->matcher->match('Test', $products);

        $this->assertArrayHasKey('status', $result);
        $this->assertArrayHasKey('product_id', $result);
        $this->assertArrayHasKey('product_name', $result);
        $this->assertArrayHasKey('suggestions', $result);
    }
}
