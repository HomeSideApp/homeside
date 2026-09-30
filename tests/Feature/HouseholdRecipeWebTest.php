<?php

namespace Tests\Feature;

use App\Models\Household;
use App\Models\HouseholdMember;
use App\Models\HouseholdRecipe;
use App\Models\Permission;
use App\Models\PermissionGroup;
use App\Models\Recipe;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HouseholdRecipeWebTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected $household;

    protected function setUp(): void
    {
        parent::setUp();

        $group = PermissionGroup::create(['name' => 'Recetas']);
        Permission::create(['name' => 'view household recipes', 'route_name' => 'households.recipes.index', 'description' => 'Ver recetas del hogar', 'permission_group_id' => $group->id]);
        Permission::create(['name' => 'share recipes', 'route_name' => 'households.recipes.share', 'description' => 'Compartir recetas', 'permission_group_id' => $group->id]);

        $role = Role::create(['name' => 'Admin', 'slug' => 'admin']);
        $role->givePermissionTo(Permission::all());

        $this->user = User::factory()->create();
        $this->user->assignRole($role);

        $this->household = Household::factory()->create(['created_by' => $this->user->id]);
        HouseholdMember::create([
            'user_id' => $this->user->id,
            'household_id' => $this->household->id,
            'role' => 'admin',
            'joined_at' => now(),
        ]);
        $this->user->update(['active_household_id' => $this->household->id]);
    }

    public function test_household_recipes_page_renders_with_shared_recipes(): void
    {
        $recipe = Recipe::factory()->create([
            'created_by' => $this->user->id,
            'owner_id' => $this->user->id,
        ]);

        HouseholdRecipe::create([
            'household_id' => $this->household->id,
            'recipe_id' => $recipe->id,
            'shared_by' => $this->user->id,
        ]);

        $response = $this->actingAs($this->user)->get(route('households.recipes.index', $this->household));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('recipes/HouseholdIndex')
            ->has('householdRecipes')
            ->where('householdRecipes.total', 1)
        );
    }

    public function test_household_recipes_returns_paginated_structure(): void
    {
        $recipe = Recipe::factory()->create([
            'created_by' => $this->user->id,
            'owner_id' => $this->user->id,
        ]);

        HouseholdRecipe::create([
            'household_id' => $this->household->id,
            'recipe_id' => $recipe->id,
            'shared_by' => $this->user->id,
        ]);

        $response = $this->actingAs($this->user)->get(route('households.recipes.index', $this->household));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->has('householdRecipes.current_page')
            ->has('householdRecipes.last_page')
            ->has('householdRecipes.per_page')
            ->has('householdRecipes.total')
        );
    }

    public function test_empty_household_shows_no_recipes(): void
    {
        $response = $this->actingAs($this->user)->get(route('households.recipes.index', $this->household));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('recipes/HouseholdIndex')
            ->where('householdRecipes.total', 0)
        );
    }

    public function test_search_filters_household_recipes(): void
    {
        $paella = Recipe::factory()->create([
            'name' => 'Paella Valenciana',
            'created_by' => $this->user->id,
            'owner_id' => $this->user->id,
        ]);
        $tortilla = Recipe::factory()->create([
            'name' => 'Tortilla de patatas',
            'created_by' => $this->user->id,
            'owner_id' => $this->user->id,
        ]);

        HouseholdRecipe::create([
            'household_id' => $this->household->id,
            'recipe_id' => $paella->id,
            'shared_by' => $this->user->id,
        ]);
        HouseholdRecipe::create([
            'household_id' => $this->household->id,
            'recipe_id' => $tortilla->id,
            'shared_by' => $this->user->id,
        ]);

        $response = $this->actingAs($this->user)->get(route('households.recipes.index', ['household' => $this->household->id, 'search' => 'Paella']));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('householdRecipes.total', 1)
        );
    }
}
