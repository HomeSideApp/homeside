<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\PermissionGroup;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CooklangImportTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $group = PermissionGroup::create(['name' => 'Recetas']);
        Permission::create(['name' => 'import recipes', 'route_name' => 'recipes.import', 'description' => 'Importar recetas', 'permission_group_id' => $group->id]);
        Permission::create(['name' => 'import recipes cooklang', 'route_name' => 'recipes.import.cooklang', 'description' => 'Importar recetas Cooklang', 'permission_group_id' => $group->id]);
        Permission::create(['name' => 'store recipes', 'route_name' => 'recipes.store', 'description' => 'Crear recetas', 'permission_group_id' => $group->id]);
        Permission::create(['name' => 'import recipes review', 'route_name' => 'recipes.import.review', 'description' => 'Revisar importación de recetas', 'permission_group_id' => $group->id]);

        $role = Role::create(['name' => 'user', 'slug' => 'user']);
        $role->syncPermissions(['import recipes', 'import recipes cooklang', 'store recipes', 'import recipes review']);

        $this->user = User::factory()->create();
        $this->user->assignRole($role);
    }

    public function test_cooklang_import_returns_recipe_data(): void
    {
        $cooklang = <<<'COOKLANG'
            ---
            title: California Veggie Wraps
            tags:
              - lunch
            ---

            @whole wheat tortilla{4}
            @lettuce{2%cup}
            @shredded carrots{1%cup}
            @sprouts{1%cup}
            @avocado{1}
            @bell pepper{1}
            @jack cheese{0.5%cup}

            #knife{1}

            Spread on each @whole wheat tortilla{}.
            Top with @lettuce{}, @shredded carrots{}, @sprouts{}, @avocado{}, and @jack cheese{}.
            COOKLANG;

        $response = $this->actingAs($this->user)
            ->postJson(route('recipes.import.cooklang'), [
                'cooklang' => $cooklang,
            ], ['X-Requested-With' => 'XMLHttpRequest']);

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'recipe' => ['name', 'ingredients', 'steps', 'cookware'],
                    'warnings',
                    'matches',
                ],
            ])
            ->assertJsonPath('data.recipe.name', 'California Veggie Wraps');
    }

    public function test_cooklang_import_validates_required_field(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson(route('recipes.import.cooklang'), [], ['X-Requested-With' => 'XMLHttpRequest']);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors('cooklang');
    }

    public function test_cooklang_import_validates_minimum_length(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson(route('recipes.import.cooklang'), [
                'cooklang' => 'short',
            ], ['X-Requested-With' => 'XMLHttpRequest']);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors('cooklang');
    }

    public function test_cooklang_import_parses_ingredients(): void
    {
        $cooklang = <<<'COOKLANG'
            @flour{2%cup}
            @sugar{1/2%cup}

            Mix @flour{} and @sugar{}.
            COOKLANG;

        $response = $this->actingAs($this->user)
            ->postJson(route('recipes.import.cooklang'), [
                'cooklang' => $cooklang,
            ], ['X-Requested-With' => 'XMLHttpRequest']);

        $response->assertOk()
            ->assertJsonCount(2, 'data.recipe.ingredients')
            ->assertJsonPath('data.recipe.ingredients.0.name', 'flour')
            ->assertJsonPath('data.recipe.ingredients.0.quantity', 2)
            ->assertJsonPath('data.recipe.ingredients.0.unit', 'cup');
    }

    public function test_unauthenticated_user_cannot_import_cooklang(): void
    {
        $response = $this->postJson(route('recipes.import.cooklang'), [
            'cooklang' => "Mix @flour{}.\n",
        ]);

        $response->assertUnauthorized();
    }

    public function test_review_renders_import_confirm_page(): void
    {
        $recipeData = [
            'name' => 'Receta de prueba',
            'description' => null,
            'servings' => 4,
            'ingredients' => [
                ['name' => 'harina', 'quantity' => 2, 'unit' => 'cup', 'product_id' => null, 'preparation' => null, 'notes' => null, 'optional' => false, 'quantity_text' => null, 'section_id' => null, 'order' => 0, 'client_id' => null, 'id' => null],
            ],
            'steps' => [
                ['description' => 'Mezclar todo', 'image_path' => null, 'section_id' => null, 'ingredients' => [], 'cookware' => [], 'timers' => [], 'order' => 0, 'client_id' => null, 'id' => null],
            ],
            'cookware' => [],
            'sections' => [],
            'tags' => [],
        ];

        $this->actingAs($this->user)
            ->postJson(route('recipes.import.review'), [
                'recipe' => json_encode($recipeData),
            ])
            ->assertRedirect();

        $response = $this->actingAs($this->user)
            ->get(route('recipes.import.review'));

        $response->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('recipes/ImportConfirm')
                ->has('recipe')
                ->has('products')
                ->has('cookware')
                ->where('recipe.name', 'Receta de prueba')
                ->where('recipe.servings', 4)
            );
    }

    public function test_review_validates_recipe_required(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson(route('recipes.import.review'), []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors('recipe');
    }

    public function test_unauthenticated_user_cannot_review(): void
    {
        $recipeData = ['name' => 'Test', 'ingredients' => [], 'steps' => [], 'cookware' => [], 'sections' => [], 'tags' => []];

        $response = $this->postJson(route('recipes.import.review'), [
            'recipe' => json_encode($recipeData),
        ]);

        $response->assertUnauthorized();
    }
}
