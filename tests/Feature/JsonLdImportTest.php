<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\PermissionGroup;
use App\Models\Role;
use App\Models\User;
use App\Services\Recipes\Import\SafeUrlFetcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Tests\Fixtures\FakeSafeUrlFetcher;
use Tests\TestCase;

class JsonLdImportTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected FakeSafeUrlFetcher $fetcher;

    protected function setUp(): void
    {
        parent::setUp();

        $group = PermissionGroup::create(['name' => 'Recetas']);
        Permission::create(['name' => 'import recipes', 'route_name' => 'recipes.import', 'description' => 'Importar recetas', 'permission_group_id' => $group->id]);
        Permission::create(['name' => 'import recipes json-ld', 'route_name' => 'recipes.import.json-ld', 'description' => 'Importar recetas JSON-LD', 'permission_group_id' => $group->id]);
        Permission::create(['name' => 'store recipes', 'route_name' => 'recipes.store', 'description' => 'Crear recetas', 'permission_group_id' => $group->id]);
        Permission::create(['name' => 'import recipes review', 'route_name' => 'recipes.import.review', 'description' => 'Revisar importación de recetas', 'permission_group_id' => $group->id]);

        $role = Role::create(['name' => 'user', 'slug' => 'user']);
        $role->syncPermissions(['import recipes', 'import recipes json-ld', 'store recipes', 'import recipes review']);

        $this->user = User::factory()->create();
        $this->user->assignRole($role);

        $this->fetcher = new FakeSafeUrlFetcher;
        $this->app->instance(SafeUrlFetcher::class, $this->fetcher);
    }

    public function test_safe_url_http_macro_is_registered(): void
    {
        $this->assertInstanceOf(PendingRequest::class, Http::safeUrl());
    }

    public function test_jsonld_import_returns_recipe_data(): void
    {
        $this->fetcher->whenUrl('https://example.com/receta', $this->buildHtmlWithJsonLd([
            '@type' => 'Recipe',
            'name' => 'Tortilla de Patatas',
            'description' => 'La clásica tortilla española',
            'recipeIngredient' => [
                '4 huevos',
                '4 patatas',
                'Aceite de oliva',
                'Sal',
            ],
            'recipeInstructions' => [
                'Pelar y cortar las patatas.',
                'Freír las patatas en aceite.',
                'Batir los huevos y mezclar.',
                'Cocinar la tortilla.',
            ],
            'prepTime' => 'PT15M',
            'cookTime' => 'PT20M',
            'recipeYield' => 4,
            'keywords' => ['española', 'tradicional'],
            'suitableForDiet' => 'https://schema.org/GlutenFreeDiet',
        ]));

        $response = $this->actingAs($this->user)
            ->postJson(route('recipes.import.json-ld'), [
                'url' => 'https://example.com/receta',
            ], ['X-Requested-With' => 'XMLHttpRequest']);

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'recipe' => ['name', 'ingredients', 'steps'],
                    'warnings',
                    'matches',
                ],
            ])
            ->assertJsonPath('data.recipe.name', 'Tortilla de Patatas');
        $response->assertJsonPath('data.recipe.tags', ['española', 'tradicional'])
            ->assertJsonPath('data.recipe.suitable_for_diet', ['https://schema.org/GlutenFreeDiet'])
            ->assertJsonPath('data.recipe.source_url', 'https://example.com/receta');
    }

    public function test_jsonld_import_validates_required_url(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson(route('recipes.import.json-ld'), [], ['X-Requested-With' => 'XMLHttpRequest']);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors('url');
    }

    public function test_jsonld_import_validates_url_format(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson(route('recipes.import.json-ld'), [
                'url' => 'not-a-url',
            ], ['X-Requested-With' => 'XMLHttpRequest']);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors('url');
    }

    public function test_jsonld_import_returns_error_when_no_jsonld_found(): void
    {
        $this->fetcher->whenUrl('https://example.com/no-recipe', '<html><head><title>No Recipe</title></head><body><p>Just a page.</p></body></html>');

        $response = $this->actingAs($this->user)
            ->postJson(route('recipes.import.json-ld'), [
                'url' => 'https://example.com/no-recipe',
            ], ['X-Requested-With' => 'XMLHttpRequest']);

        $response->assertStatus(422)
            ->assertJsonPath('error', 'No se encontraron datos de receta (JSON-LD) en esta URL.')
            ->assertJsonPath('data.recipe', null);
    }

    public function test_jsonld_import_returns_error_when_jsonld_is_not_recipe(): void
    {
        $html = <<<'HTML'
            <html><head>
            <script type="application/ld+json">
            {"@context":"https://schema.org","@type":"NewsArticle","headline":"Not a recipe"}
            </script>
            </head><body></body></html>
            HTML;

        $this->fetcher->whenUrl('https://example.com/article', $html);

        $response = $this->actingAs($this->user)
            ->postJson(route('recipes.import.json-ld'), [
                'url' => 'https://example.com/article',
            ], ['X-Requested-With' => 'XMLHttpRequest']);

        $response->assertStatus(422)
            ->assertJsonPath('data.recipe', null)
            ->assertJsonFragment(['error' => 'Se encontró contenido JSON-LD (tipo: NewsArticle), pero no es una receta. La página debe tener un bloque @type: Recipe con ingredientes e instrucciones.']);
    }

    public function test_jsonld_import_returns_error_when_fetch_fails(): void
    {
        $this->fetcher->whenFail('Connection refused');

        $response = $this->actingAs($this->user)
            ->postJson(route('recipes.import.json-ld'), [
                'url' => 'https://example.com/broken',
            ], ['X-Requested-With' => 'XMLHttpRequest']);

        $response->assertStatus(422)
            ->assertJsonPath('data.recipe', null);
    }

    public function test_jsonld_import_parses_ingredients(): void
    {
        $this->fetcher->whenUrl('https://example.com/galletas', $this->buildHtmlWithJsonLd([
            '@type' => 'Recipe',
            'name' => 'Galletas',
            'recipeIngredient' => [
                '200g harina',
                '100g mantequilla',
                '50g azúcar',
            ],
            'recipeInstructions' => [
                'Mezclar ingredientes.',
                'Hornear 15 minutos.',
            ],
        ]));

        $response = $this->actingAs($this->user)
            ->postJson(route('recipes.import.json-ld'), [
                'url' => 'https://example.com/galletas',
            ], ['X-Requested-With' => 'XMLHttpRequest']);

        $response->assertOk()
            ->assertJsonCount(3, 'data.recipe.ingredients')
            ->assertJsonPath('data.recipe.ingredients.0.name', 'harina');
    }

    public function test_jsonld_import_with_graph_wrapper(): void
    {
        $html = <<<'HTML'
            <html><head>
            <script type="application/ld+json">
            {
                "@context": "https://schema.org",
                "@graph": [
                    {
                        "@type": "Recipe",
                        "name": "Sopa de Tomate",
                        "recipeIngredient": ["Tomate", "Ajo"],
                        "recipeInstructions": ["Cocinar la sopa."]
                    }
                ]
            }
            </script>
            </head><body></body></html>
            HTML;

        $this->fetcher->whenUrl('https://example.com/sopa', $html);

        $response = $this->actingAs($this->user)
            ->postJson(route('recipes.import.json-ld'), [
                'url' => 'https://example.com/sopa',
            ], ['X-Requested-With' => 'XMLHttpRequest']);

        $response->assertOk()
            ->assertJsonPath('data.recipe.name', 'Sopa de Tomate');
    }

    public function test_unauthenticated_user_cannot_import_jsonld(): void
    {
        $response = $this->postJson(route('recipes.import.json-ld'), [
            'url' => 'https://example.com/receta',
        ]);

        $response->assertUnauthorized();
    }

    /**
     * Build an HTML page with a JSON-LD script block containing recipe data.
     */
    private function buildHtmlWithJsonLd(array $recipe): string
    {
        $json = json_encode($recipe, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        return <<<HTML
            <html><head>
            <script type="application/ld+json">
            {$json}
            </script>
            </head><body></body></html>
            HTML;
    }
}
