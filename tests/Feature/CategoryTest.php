<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategoryTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
    }

    public function test_unauthenticated_user_cannot_list_categories(): void
    {
        $this->getJson(route('api.categories'))
            ->assertUnauthorized();
    }

    public function test_categories_are_returned_ordered_by_sort_order(): void
    {
        Category::create(['name' => 'B', 'slug' => 'b', 'sort_order' => 2, 'color' => '#FF0000']);
        Category::create(['name' => 'A', 'slug' => 'a', 'sort_order' => 1, 'color' => '#00FF00']);

        $response = $this->actingAs($this->user)
            ->getJson(route('api.categories'));

        $response->assertOk()
            ->assertJsonCount(2)
            ->assertJsonPath('0.name', 'A')
            ->assertJsonPath('1.name', 'B');
    }

    public function test_category_includes_color_and_icon(): void
    {
        Category::create(['name' => 'Frutas', 'slug' => 'frutas', 'color' => '#26A69A', 'icon' => 'apple.webp']);

        $response = $this->actingAs($this->user)
            ->getJson(route('api.categories'));

        $response->assertOk()
            ->assertJsonPath('0.color', '#26A69A')
            ->assertJsonPath('0.icon', 'apple.webp');
    }
}
