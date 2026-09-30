<?php

declare(strict_types=1);

namespace Tests\Unit\Ai;

use App\Ai\Tools\GetAvailableIngredientsTool;
use App\Ai\Tools\GetRecipeTool;
use App\Ai\Tools\GetShoppingListItemsTool;
use App\Ai\Tools\GetShoppingListsTool;
use App\Ai\Tools\SearchAccessibleRecipesTool;
use App\Ai\Tools\SendActionProposalTool;
use App\Models\Household;
use App\Models\HouseholdRecipe;
use App\Models\ListItem;
use App\Models\Product;
use App\Models\Recipe;
use App\Models\ShoppingList;
use App\Models\User;
use HomeSide\AiAgents\Execution\AiExecutionContextData;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\JsonSchema\JsonSchemaTypeFactory;
use Laravel\Ai\Tools\Request;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ToolsTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Household $household;

    private User $otherUser;

    private AiExecutionContextData $context;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->otherUser = User::factory()->create();
        $this->household = Household::factory()->create(['created_by' => $this->user->id]);

        $this->context = new AiExecutionContextData(
            userId: $this->user->id,
        );
    }

    #[Test]
    public function action_proposals_only_expose_supported_types(): void
    {
        $schema = (new SendActionProposalTool($this->context))->schema(new JsonSchemaTypeFactory);

        $this->assertSame(
            ['add_shopping_items', 'create_recipe'],
            $schema['type']->toArray()['enum'],
        );
    }

    /**
     * Add a user to a household as a member.
     */
    private function addMember(User $user, Household $household): void
    {
        $household->members()->create([
            'user_id' => $user->id,
            'role' => 'member',
            'joined_at' => now(),
        ]);
    }

    // --- SearchAccessibleRecipesTool ---

    #[Test]
    public function search_recipes_returns_user_recipes(): void
    {
        $recipe = Recipe::factory()->create([
            'name' => 'Tortilla de patatas',
            'created_by' => $this->user->id,
        ]);

        $tool = new SearchAccessibleRecipesTool($this->context);
        $result = json_decode($tool->handle(new Request(['query' => 'tortilla'])), true);

        $this->assertNotEmpty($result);
        $this->assertSame('Tortilla de patatas', $result[0]['name']);
    }

    #[Test]
    public function search_recipes_respects_limit_cap(): void
    {
        // Crear 25 recetas
        for ($i = 0; $i < 25; $i++) {
            Recipe::factory()->create([
                'name' => "Receta {$i}",
                'created_by' => $this->user->id,
            ]);
        }

        $tool = new SearchAccessibleRecipesTool($this->context);
        $result = json_decode($tool->handle(new Request(['query' => '', 'limit' => 100])), true);

        // Máximo 20 (MAX_LIMIT)
        $this->assertLessThanOrEqual(20, count($result));
    }

    #[Test]
    public function search_recipes_does_not_return_other_user_private_recipes(): void
    {
        // Receta del otro usuario (no compartida)
        Recipe::factory()->create([
            'name' => 'Receta privada del otro',
            'created_by' => $this->otherUser->id,
        ]);

        $tool = new SearchAccessibleRecipesTool($this->context);
        $result = json_decode($tool->handle(new Request(['query' => 'privada'])), true);

        $this->assertEmpty($result);
    }

    #[Test]
    public function search_recipes_returns_empty_for_nonexistent_query(): void
    {
        Recipe::factory()->create([
            'name' => 'Tortilla',
            'created_by' => $this->user->id,
        ]);

        $tool = new SearchAccessibleRecipesTool($this->context);
        $result = json_decode($tool->handle(new Request(['query' => 'xyz_no_existe'])), true);

        $this->assertEmpty($result);
    }

    // --- GetRecipeTool ---

    #[Test]
    public function get_recipe_returns_full_recipe(): void
    {
        $recipe = Recipe::factory()->create([
            'name' => 'Paella',
            'created_by' => $this->user->id,
        ]);

        $tool = new GetRecipeTool($this->context);
        $result = json_decode($tool->handle(new Request(['recipe_id' => $recipe->id])), true);

        $this->assertSame('Paella', $result['name']);
    }

    #[Test]
    public function get_recipe_throws_for_other_user_recipe(): void
    {
        $recipe = Recipe::factory()->create([
            'name' => 'Receta privada',
            'created_by' => $this->otherUser->id,
        ]);

        $tool = new GetRecipeTool($this->context);

        $this->expectException(ModelNotFoundException::class);

        $tool->handle(new Request(['recipe_id' => $recipe->id]));
    }

    // --- GetAvailableIngredientsTool ---

    #[Test]
    public function search_ingredients_returns_user_products(): void
    {
        Product::factory()->create([
            'name' => 'Tomate',
            'created_by' => $this->user->id,
            'is_active' => true,
        ]);

        $tool = new GetAvailableIngredientsTool($this->context);
        $result = json_decode($tool->handle(new Request(['query' => 'tomate'])), true);

        $this->assertNotEmpty($result);
        $this->assertSame('Tomate', $result[0]['name']);
    }

    #[Test]
    public function search_ingredients_respects_limit_cap(): void
    {
        for ($i = 0; $i < 60; $i++) {
            Product::factory()->create([
                'name' => "Producto {$i}",
                'slug' => "producto-{$i}-".uniqid(),
                'created_by' => $this->user->id,
                'is_active' => true,
            ]);
        }

        $tool = new GetAvailableIngredientsTool($this->context);
        $result = json_decode($tool->handle(new Request(['query' => '', 'limit' => 200])), true);

        // Máximo 50 (MAX_LIMIT)
        $this->assertLessThanOrEqual(50, count($result));
    }

    #[Test]
    public function search_ingredients_excludes_inactive_products(): void
    {
        Product::factory()->create([
            'name' => 'Producto activo',
            'created_by' => $this->user->id,
            'is_active' => true,
        ]);

        Product::factory()->create([
            'name' => 'Producto inactivo',
            'created_by' => $this->user->id,
            'is_active' => false,
        ]);

        $tool = new GetAvailableIngredientsTool($this->context);
        $result = json_decode($tool->handle(new Request(['query' => ''])), true);

        $this->assertCount(1, $result);
        $this->assertSame('Producto activo', $result[0]['name']);
    }

    #[Test]
    public function search_ingredients_returns_products_from_the_shared_global_catalog(): void
    {
        Product::factory()->create([
            'name' => 'Producto del otro',
            'slug' => 'producto-del-otro-'.uniqid(),
            'created_by' => $this->otherUser->id,
            'is_active' => true,
        ]);

        $tool = new GetAvailableIngredientsTool($this->context);
        $result = json_decode($tool->handle(new Request(['query' => 'Producto del otro'])), true);

        $this->assertCount(1, $result);
        $this->assertSame('Producto del otro', $result[0]['name']);
    }

    // --- Cross-household recipe visibility (RecipePolicy parity) ---

    #[Test]
    public function search_recipes_returns_recipes_shared_with_a_user_household(): void
    {
        $this->addMember($this->user, $this->household);

        $recipe = Recipe::factory()->create([
            'name' => 'Croquetas de la abuela',
            'created_by' => $this->otherUser->id,
            'owner_id' => $this->otherUser->id,
        ]);
        HouseholdRecipe::create([
            'household_id' => $this->household->id,
            'recipe_id' => $recipe->id,
            'shared_by' => $this->otherUser->id,
        ]);

        $tool = new SearchAccessibleRecipesTool($this->context);
        $result = json_decode($tool->handle(new Request(['query' => 'Croquetas'])), true);

        $this->assertCount(1, $result);
        $this->assertSame($recipe->id, $result[0]['id']);
    }

    #[Test]
    public function search_recipes_spans_every_household_the_user_belongs_to(): void
    {
        $secondHousehold = Household::factory()->create(['created_by' => $this->otherUser->id]);
        $this->addMember($this->user, $this->household);
        $this->addMember($this->user, $secondHousehold);

        $firstRecipe = Recipe::factory()->create([
            'name' => 'Guiso del hogar uno',
            'created_by' => $this->otherUser->id,
            'owner_id' => $this->otherUser->id,
        ]);
        $secondRecipe = Recipe::factory()->create([
            'name' => 'Guiso del hogar dos',
            'created_by' => $this->otherUser->id,
            'owner_id' => $this->otherUser->id,
        ]);
        HouseholdRecipe::create([
            'household_id' => $this->household->id,
            'recipe_id' => $firstRecipe->id,
            'shared_by' => $this->otherUser->id,
        ]);
        HouseholdRecipe::create([
            'household_id' => $secondHousehold->id,
            'recipe_id' => $secondRecipe->id,
            'shared_by' => $this->otherUser->id,
        ]);

        $tool = new SearchAccessibleRecipesTool($this->context);
        $result = json_decode($tool->handle(new Request(['query' => 'Guiso', 'limit' => 20])), true);

        $this->assertEqualsCanonicalizing(
            [$firstRecipe->id, $secondRecipe->id],
            array_column($result, 'id'),
        );
    }

    #[Test]
    public function search_recipes_excludes_recipes_shared_with_foreign_households(): void
    {
        $foreignHousehold = Household::factory()->create(['created_by' => $this->otherUser->id]);

        $shared = Recipe::factory()->create([
            'name' => 'Secreto ajeno',
            'created_by' => $this->otherUser->id,
            'owner_id' => $this->otherUser->id,
        ]);
        HouseholdRecipe::create([
            'household_id' => $foreignHousehold->id,
            'recipe_id' => $shared->id,
            'shared_by' => $this->otherUser->id,
        ]);

        $tool = new SearchAccessibleRecipesTool($this->context);
        $result = json_decode($tool->handle(new Request(['query' => 'Secreto'])), true);

        $this->assertEmpty($result);
    }

    #[Test]
    public function get_recipe_returns_recipe_shared_with_a_user_household(): void
    {
        $this->addMember($this->user, $this->household);

        $recipe = Recipe::factory()->create([
            'name' => 'Fabada compartida',
            'created_by' => $this->otherUser->id,
            'owner_id' => $this->otherUser->id,
        ]);
        HouseholdRecipe::create([
            'household_id' => $this->household->id,
            'recipe_id' => $recipe->id,
            'shared_by' => $this->otherUser->id,
        ]);

        $tool = new GetRecipeTool($this->context);
        $result = json_decode($tool->handle(new Request(['recipe_id' => $recipe->id])), true);

        $this->assertSame('Fabada compartida', $result['name']);
    }

    #[Test]
    public function get_recipe_still_denies_recipes_not_visible_to_the_user(): void
    {
        $recipe = Recipe::factory()->create([
            'name' => 'Fabada privada',
            'created_by' => $this->otherUser->id,
            'owner_id' => $this->otherUser->id,
        ]);

        $tool = new GetRecipeTool($this->context);

        $this->expectException(ModelNotFoundException::class);

        $tool->handle(new Request(['recipe_id' => $recipe->id]));
    }

    // --- GetShoppingListsTool ---

    #[Test]
    public function shopping_lists_span_every_household_the_user_belongs_to(): void
    {
        $secondHousehold = Household::factory()->create(['created_by' => $this->otherUser->id]);
        $this->addMember($this->user, $this->household);
        $this->addMember($this->user, $secondHousehold);

        $firstList = ShoppingList::factory()->create([
            'name' => 'Compra hogar uno',
            'household_id' => $this->household->id,
            'created_by' => $this->otherUser->id,
        ]);
        $secondList = ShoppingList::factory()->create([
            'name' => 'Compra hogar dos',
            'household_id' => $secondHousehold->id,
            'created_by' => $this->otherUser->id,
        ]);
        $personalList = ShoppingList::factory()->create([
            'name' => 'Compra personal',
            'household_id' => null,
            'created_by' => $this->user->id,
        ]);

        $tool = new GetShoppingListsTool($this->context);
        $result = json_decode($tool->handle(new Request(['limit' => 10])), true);

        $this->assertEqualsCanonicalizing(
            [$firstList->id, $secondList->id, $personalList->id],
            array_column($result, 'id'),
        );
    }

    #[Test]
    public function shopping_lists_exclude_households_the_user_is_not_a_member_of(): void
    {
        $foreignHousehold = Household::factory()->create(['created_by' => $this->otherUser->id]);
        ShoppingList::factory()->create([
            'name' => 'Compra ajena',
            'household_id' => $foreignHousehold->id,
            'created_by' => $this->otherUser->id,
        ]);

        $tool = new GetShoppingListsTool($this->context);
        $result = json_decode($tool->handle(new Request([])), true);

        $this->assertEmpty($result);
    }

    #[Test]
    public function shopping_lists_exclude_lists_of_a_household_the_user_was_removed_from(): void
    {
        $this->addMember($this->user, $this->household);
        $list = ShoppingList::factory()->create([
            'name' => 'Compra del hogar',
            'household_id' => $this->household->id,
            'created_by' => $this->otherUser->id,
        ]);

        $this->household->members()->where('user_id', $this->user->id)->delete();

        $tool = new GetShoppingListsTool($this->context);
        $result = json_decode($tool->handle(new Request([])), true);

        $this->assertEmpty($result);
        $this->assertNotContains($list->id, array_column($result, 'id'));
    }

    // --- GetShoppingListItemsTool ---

    #[Test]
    public function shopping_list_items_returns_items_of_an_accessible_household_list(): void
    {
        $this->addMember($this->user, $this->household);
        $list = ShoppingList::factory()->create([
            'household_id' => $this->household->id,
            'created_by' => $this->otherUser->id,
        ]);
        ListItem::factory()->create([
            'list_id' => $list->id,
            'product_id' => null,
            'custom_name' => 'Leche',
        ]);

        $tool = new GetShoppingListItemsTool($this->context);
        $result = json_decode($tool->handle(new Request(['list_id' => $list->id])), true);

        $this->assertCount(1, $result);
        $this->assertSame('Leche', $result[0]['custom_name']);
    }

    #[Test]
    public function shopping_list_items_denies_access_to_a_foreign_household_list(): void
    {
        $foreignHousehold = Household::factory()->create(['created_by' => $this->otherUser->id]);
        $list = ShoppingList::factory()->create([
            'household_id' => $foreignHousehold->id,
            'created_by' => $this->otherUser->id,
        ]);
        ListItem::factory()->create([
            'list_id' => $list->id,
            'product_id' => null,
            'custom_name' => 'Secreto',
        ]);

        $tool = new GetShoppingListItemsTool($this->context);
        $result = json_decode($tool->handle(new Request(['list_id' => $list->id])), true);

        $this->assertSame(['error' => __('app.ai_tools.shopping_list_items.not_accessible')], $result);
    }

    #[Test]
    public function shopping_list_items_denies_access_after_being_removed_from_the_household(): void
    {
        $this->addMember($this->user, $this->household);
        $list = ShoppingList::factory()->create([
            'household_id' => $this->household->id,
            'created_by' => $this->otherUser->id,
        ]);
        ListItem::factory()->create([
            'list_id' => $list->id,
            'product_id' => null,
            'custom_name' => 'Leche',
        ]);

        $this->household->members()->where('user_id', $this->user->id)->delete();

        $tool = new GetShoppingListItemsTool($this->context);
        $result = json_decode($tool->handle(new Request(['list_id' => $list->id])), true);

        $this->assertSame(['error' => __('app.ai_tools.shopping_list_items.not_accessible')], $result);
    }
}
