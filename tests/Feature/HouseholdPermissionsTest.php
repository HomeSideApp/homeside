<?php

namespace Tests\Feature;

use App\Actions\Ai\AcceptAiActionProposal;
use App\Actions\Economy\GetHouseholdsComparison;
use App\Actions\Households\AcceptInvitation;
use App\Actions\Households\DeleteHousehold;
use App\Actions\Households\InviteMember;
use App\Actions\Households\RemoveMember;
use App\Actions\Recipes\AddToShoppingList;
use App\Actions\ShoppingLists\ListShoppingLists;
use App\Ai\Tools\GetShoppingListsTool;
use App\Data\Households\InviteMemberData;
use App\Data\Recipes\AddToListData;
use App\Enums\HouseholdAccessProfile;
use App\Enums\HouseholdRole;
use App\Models\Contact;
use App\Models\ContactSource;
use App\Models\EconomicDocument;
use App\Models\EconomicTransaction;
use App\Models\Household;
use App\Models\HouseholdMember;
use App\Models\HouseholdRecipe;
use App\Models\ListItem;
use App\Models\Recipe;
use App\Models\RecipeIngredient;
use App\Models\RecipeReference;
use App\Models\RecipeStep;
use App\Models\ShoppingList;
use App\Models\User;
use App\Support\HouseholdAccess;
use Database\Seeders\RolesAndPermissionsSeeder;
use HomeSide\AiAgents\Execution\AiExecutionContextData;
use HomeSide\AiAgents\Models\AiActionProposal;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Laravel\Ai\Tools\Request;
use PHPUnit\Framework\Attributes\TestWith;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class HouseholdPermissionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_recipe_ingredients_require_collaboration_in_the_recipe_and_destination(): void
    {
        [$owner, $household] = $this->household();
        $user = User::factory()->create();
        $member = HouseholdMember::factory()->create(['household_id' => $household->id, 'user_id' => $user->id, 'access_profile' => 'reader']);
        $recipe = Recipe::factory()->create(['owner_id' => $owner->id]);
        HouseholdRecipe::create(['household_id' => $household->id, 'recipe_id' => $recipe->id, 'shared_by' => $owner->id]);
        $list = ShoppingList::factory()->create(['household_id' => null, 'created_by' => $user->id]);
        RecipeIngredient::factory()->create(['recipe_id' => $recipe->id, 'optional' => false]);
        $action = app(AddToShoppingList::class);
        try {
            $action->execute($recipe, $list, new AddToListData(1), $user);
            $this->fail('Reading a shared recipe must not permit adding ingredients.');
        } catch (HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }
        $member->update(['module_access' => array_replace($member->permissions(), ['recipes' => 'write'])]);
        $this->assertCount(1, $action->execute($recipe, $list, new AddToListData(1), $user));
        $foreignList = ShoppingList::factory()->create(['household_id' => $household->id]);
        $this->expectException(AuthorizationException::class);
        $action->execute($recipe, $foreignList, new AddToListData(1), $user);
    }

    public function test_personal_recipe_does_not_embed_an_original_after_household_access_is_revoked(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        [$owner, $household] = $this->household();
        $user = User::factory()->create();
        $user->assignRole('user');
        $member = HouseholdMember::factory()->create(['household_id' => $household->id, 'user_id' => $user->id, 'access_profile' => 'reader']);
        $source = Recipe::factory()->create(['owner_id' => $owner->id]);
        HouseholdRecipe::create(['household_id' => $household->id, 'recipe_id' => $source->id, 'shared_by' => $owner->id]);
        $personal = Recipe::factory()->create(['owner_id' => $user->id, 'derived_from_recipe_id' => $source->id]);
        $step = RecipeStep::factory()->create(['recipe_id' => $personal->id]);
        RecipeReference::factory()->create(['recipe_id' => $personal->id, 'recipe_step_id' => $step->id, 'referenced_recipe_id' => $source->id]);
        $this->actingAs($user)->getJson(route('api.v1.recipes.show', $personal))->assertOk()->assertJsonPath('data.derived_from.id', $source->id);
        $member->update(['module_access' => array_replace($member->permissions(), ['recipes' => 'none'])]);
        $this->getJson(route('api.v1.recipes.show', $personal))->assertOk()->assertJsonPath('data.derived_from', null);
        $this->get(route('recipes.cook', $personal))->assertOk()->assertInertia(fn ($page) => $page->where('recipe.derived_from', null)->where('recipe.steps.0.recipe_references.0.referenced_recipe', null));
        $this->get(route('recipes.edit', $personal))->assertOk()->assertInertia(fn ($page) => $page->where('recipe.steps.0.recipe_references.0.referenced_recipe', null));
    }

    public function test_recipe_detail_hides_other_households_after_read_access_is_revoked(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        [$owner, $household] = $this->household();
        $owner->assignRole('user');
        $user = User::factory()->create();
        $user->assignRole('user');
        $member = HouseholdMember::factory()->create(['household_id' => $household->id, 'user_id' => $user->id, 'access_profile' => 'reader']);
        $other = Household::factory()->create();
        $recipe = Recipe::factory()->create(['owner_id' => $owner->id]);
        foreach ([$household, $other] as $destination) {
            HouseholdRecipe::create(['household_id' => $destination->id, 'recipe_id' => $recipe->id, 'shared_by' => $owner->id]);
        }
        $this->actingAs($user)->get(route('recipes.show', $recipe))->assertOk()->assertInertia(fn ($page) => $page
            ->has('recipe.households', 1)->where('recipe.households.0.id', $household->id));
        $member->update(['module_access' => array_replace($member->permissions(), ['recipes' => 'none'])]);
        $this->get(route('recipes.show', $recipe))->assertForbidden();
    }

    public function test_reader_cannot_write_lists_and_personal_lists_remain_owned(): void
    {
        $reader = HouseholdMember::factory()->create(['access_profile' => HouseholdAccessProfile::Reader]);
        $list = ShoppingList::factory()->create(['household_id' => $reader->household_id]);
        $this->assertTrue($reader->user->can('view', $list));
        $this->assertFalse($reader->user->can('update', $list));
        $private = ShoppingList::factory()->create(['household_id' => null, 'created_by' => $reader->user_id]);
        $this->assertTrue($reader->user->can('update', $private));
        $reader->household->modules()->where('module', 'shopping_lists')->update(['enabled' => false]);
        app(HouseholdAccess::class)->forget($reader->user_id);
        $this->assertFalse($reader->user->can('view', $list));
    }

    public function test_reader_economy_is_equal_on_web_and_api_and_excludes_private_data(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        [$owner, $household] = $this->household();
        $reader = User::factory()->create();
        $reader->assignRole('user');
        HouseholdMember::factory()->create(['household_id' => $household->id, 'user_id' => $reader->id, 'access_profile' => 'reader']);
        $shared = EconomicTransaction::factory()->forHousehold($household)->createdBy($owner)->shared()->create();
        $private = EconomicTransaction::factory()->forHousehold($household)->createdBy($owner)->create();
        $this->actingAs($reader)->get(route('households.economy.show', [$household, $shared]))->assertOk();
        $this->getJson(route('api.v1.households.economy.transactions.show', [$household, $shared]))->assertOk();
        $this->get(route('households.economy.show', [$household, $private]))->assertForbidden();
        $this->getJson(route('api.v1.households.economy.transactions.show', [$household, $private]))->assertForbidden();
        $this->putJson(route('api.v1.households.economy.transactions.update', [$household, $shared]), ['title' => 'Blocked'])->assertForbidden();
        $this->get(route('households.economy.create', $household))->assertForbidden();
        $this->assertFalse($reader->can('update', $shared));
    }

    public function test_personal_list_catalog_exposes_only_allowed_households_and_their_write_capability(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $user = User::factory()->create();
        $user->assignRole('user');
        $reader = HouseholdMember::factory()->create(['user_id' => $user->id, 'access_profile' => 'reader']);
        $writer = HouseholdMember::factory()->create(['user_id' => $user->id, 'access_profile' => 'member']);
        HouseholdMember::factory()->create(['user_id' => $user->id, 'module_access' => ['shopping_lists' => 'none']]);
        $user->update(['active_household_id' => $reader->household_id]);
        $private = ShoppingList::factory()->create(['household_id' => null, 'created_by' => $user->id]);
        $this->actingAs($user)->get(route('lists.index'))->assertOk()->assertInertia(fn ($page) => $page
            ->has('households', 2)->has('lists', 1)->where('lists.0.id', $private->id)
            ->where('households', fn ($homes) => collect($homes)->keyBy('id')->get($reader->household_id)['can_write'] === false
                && collect($homes)->keyBy('id')->get($writer->household_id)['can_write'] === true));
        $this->get(route('lists.create'))->assertOk();
    }

    public function test_ai_proposal_rechecks_write_access_and_cannot_execute_twice_from_a_stale_copy(): void
    {
        $member = HouseholdMember::factory()->create();
        $user = $member->user;
        $list = ShoppingList::factory()->create(['household_id' => $member->household_id]);
        $proposal = AiActionProposal::create([
            'user_id' => $user->id, 'household_id' => $member->household_id, 'type' => 'add_shopping_items', 'status' => 'pending',
            'payload' => ['list_id' => $list->id, 'items' => [['custom_name' => 'Milk', 'quantity' => 1]]],
        ]);
        $stale = $proposal->fresh();
        app(HouseholdAccess::class)->capabilities($user, $member->household_id);
        $member->update(['module_access' => array_replace($member->permissions(), ['shopping_lists' => 'read'])]);
        $action = app(AcceptAiActionProposal::class);
        try {
            $action->execute($user, $proposal);
            $this->fail('An AI proposal must not retain revoked write access.');
        } catch (AuthorizationException) {
            $this->assertDatabaseMissing('list_items', ['list_id' => $list->id]);
            $this->assertSame('pending', $proposal->refresh()->status);
        }
        $member->update(['module_access' => array_replace($member->permissions(), ['shopping_lists' => 'write'])]);
        $action->execute($user, $proposal);
        try {
            $action->execute($user, $stale);
            $this->fail('A stale pending proposal must not execute twice.');
        } catch (HttpException $exception) {
            $this->assertSame(409, $exception->getStatusCode());
        }
        $this->assertSame(1, $list->items()->count());
    }

    public function test_revocation_removes_cross_household_totals_and_ai_results(): void
    {
        $member = HouseholdMember::factory()->create();
        $user = $member->user;
        $household = $member->household;
        $list = ShoppingList::factory()->create(['household_id' => $household->id, 'created_by' => $user->id]);
        EconomicTransaction::factory()->forHousehold($household)->createdBy($user)->shared()->create(['amount_minor' => 1234, 'occurred_at' => now()]);
        $tool = new GetShoppingListsTool(new AiExecutionContextData(userId: $user->id));
        $this->assertStringContainsString($list->id, $tool->handle(new Request([])));
        $this->assertSame(1234, app(GetHouseholdsComparison::class)->execute($user)['totals']['own_minor']);
        $member->update(['module_access' => array_replace($member->permissions(), ['economy' => 'none', 'shopping_lists' => 'none'])]);
        $this->assertSame(0, app(GetHouseholdsComparison::class)->execute($user)['totals']['own_minor']);
        $this->assertSame('[]', $tool->handle(new Request([])));
        $this->assertCount(0, app(ListShoppingLists::class)->execute($user));
    }

    public function test_shared_contacts_follow_local_levels_and_sources_require_administration(): void
    {
        $member = HouseholdMember::factory()->create(['access_profile' => 'reader']);
        $contact = Contact::create(['household_id' => $member->household_id, 'display_name' => 'Shared', 'type' => 'person']);
        $source = new ContactSource(['household_id' => $member->household_id, 'user_id' => null]);
        $this->assertTrue($member->user->can('view', $contact));
        $this->assertFalse($member->user->can('update', $contact));
        $this->assertFalse($member->user->can('update', $source));
        $member->update(['module_access' => array_replace($member->permissions(), ['contacts' => 'none'])]);
        $this->assertFalse($member->user->can('view', $contact));
        $this->assertCount(0, Contact::query()->visibleTo($member->user)->get());
    }

    public function test_all_levels_are_enforced_independently_for_each_area(): void
    {
        $member = HouseholdMember::factory()->create(['module_access' => array_fill_keys(['shopping_lists', 'recipes', 'economy', 'contacts'], 'none')]);
        foreach (['shopping_lists', 'recipes', 'economy', 'contacts'] as $area) {
            foreach (['none', 'read', 'write'] as $level) {
                $member->update(['module_access' => array_replace(array_fill_keys(['shopping_lists', 'recipes', 'economy', 'contacts'], 'none'), [$area => $level])]);
                $this->assertSame($level !== 'none', app(HouseholdAccess::class)->allows($member->user, $member->household_id, $area));
                $this->assertSame($level === 'write', app(HouseholdAccess::class)->allows($member->user, $member->household_id, $area, true));
            }
        }
    }

    public function test_global_administrator_does_not_gain_household_content_access(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $administrator = User::factory()->create();
        $administrator->assignRole('admin');
        $household = Household::factory()->create();
        $list = ShoppingList::factory()->create(['household_id' => $household->id]);
        $this->assertFalse($administrator->can('view', $list));
        $this->assertFalse(app(HouseholdAccess::class)->allows($administrator, $household, 'economy'));
        $this->actingAs($administrator)->getJson(route('api.v1.households.show', $household))->assertForbidden();
    }

    /** @return array{User, Household, HouseholdMember} */
    private function household(): array
    {
        $owner = User::factory()->create();
        $household = Household::factory()->create(['created_by' => $owner->id]);
        $admin = HouseholdMember::factory()->create(['household_id' => $household->id, 'user_id' => $owner->id, 'role' => HouseholdRole::Admin]);

        return [$owner, $household, $admin];
    }
}
