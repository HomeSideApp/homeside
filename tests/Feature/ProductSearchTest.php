<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductSearchTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
    }

    public function test_unauthenticated_user_cannot_search(): void
    {
        $this->getJson(route('api.products.search', ['q' => 'manzana']))
            ->assertUnauthorized();
    }

    public function test_search_returns_matching_products(): void
    {
        $category = Category::create(['name' => 'Frutas', 'slug' => 'frutas', 'color' => '#26A69A']);
        Product::create(['name' => 'Manzana', 'slug' => 'manzana', 'category_id' => $category->id, 'created_by' => $this->user->id]);
        Product::create(['name' => 'Naranja', 'slug' => 'naranja', 'category_id' => $category->id, 'created_by' => $this->user->id]);

        $response = $this->actingAs($this->user)
            ->getJson(route('api.products.search', ['q' => 'Manzana']));

        $response->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.name', 'Manzana');
    }

    public function test_search_returns_empty_for_no_match(): void
    {
        $category = Category::create(['name' => 'Frutas', 'slug' => 'frutas', 'color' => '#26A69A']);
        Product::create(['name' => 'Manzana', 'slug' => 'manzana', 'category_id' => $category->id, 'created_by' => $this->user->id]);

        $response = $this->actingAs($this->user)
            ->getJson(route('api.products.search', ['q' => 'xyz']));

        $response->assertOk()
            ->assertJsonCount(0);
    }

    public function test_search_returns_empty_for_short_query(): void
    {
        $response = $this->actingAs($this->user)
            ->getJson(route('api.products.search', ['q' => '']));

        $response->assertOk()
            ->assertJsonCount(0);
    }

    public function test_search_is_case_insensitive(): void
    {
        $category = Category::create(['name' => 'Frutas', 'slug' => 'frutas', 'color' => '#26A69A']);
        Product::create(['name' => 'Manzana', 'slug' => 'manzana', 'category_id' => $category->id, 'created_by' => $this->user->id]);

        $response = $this->actingAs($this->user)
            ->getJson(route('api.products.search', ['q' => 'manzana']));

        $response->assertOk()
            ->assertJsonCount(1);
    }

    public function test_search_limits_results(): void
    {
        $category = Category::create(['name' => 'Frutas', 'slug' => 'frutas', 'color' => '#26A69A']);

        for ($i = 0; $i < 25; $i++) {
            Product::create(['name' => "Producto {$i}", 'slug' => "producto-{$i}", 'category_id' => $category->id, 'created_by' => $this->user->id]);
        }

        $response = $this->actingAs($this->user)
            ->getJson(route('api.products.search', ['q' => 'Producto']));

        $response->assertOk()
            ->assertJsonCount(20);
    }

    public function test_search_includes_category_color(): void
    {
        $category = Category::create(['name' => 'Frutas', 'slug' => 'frutas', 'color' => '#26A69A']);
        Product::create(['name' => 'Manzana', 'slug' => 'manzana', 'category_id' => $category->id, 'created_by' => $this->user->id]);

        $response = $this->actingAs($this->user)
            ->getJson(route('api.products.search', ['q' => 'Manzana']));

        $response->assertOk()
            ->assertJsonPath('0.category.color', '#26A69A');
    }
}
