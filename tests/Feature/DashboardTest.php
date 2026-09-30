<?php

namespace Tests\Feature;

use App\Enums\HouseholdModule as HouseholdModuleEnum;
use App\Models\HouseholdModule;
use App\Models\ListItem;
use App\Models\Permission;
use App\Models\PermissionGroup;
use App\Models\Role;
use App\Models\ShoppingList;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\WithHousehold;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase, WithHousehold;

    protected function setUp(): void
    {
        parent::setUp();

        $group = PermissionGroup::create(['name' => 'Dashboard']);
        Permission::create(['name' => 'view dashboard', 'route_name' => 'dashboard', 'description' => 'Ver dashboard', 'permission_group_id' => $group->id]);

        $householdGroup = PermissionGroup::create(['name' => 'Hogares']);
        Permission::create(['name' => 'view households', 'route_name' => 'households.index', 'description' => 'Ver hogares', 'permission_group_id' => $householdGroup->id]);
        Permission::create(['name' => 'view household', 'route_name' => 'households.show', 'description' => 'Ver un hogar', 'permission_group_id' => $householdGroup->id]);
        Permission::create(['name' => 'switch household', 'route_name' => 'households.switch', 'description' => 'Cambiar hogar activo', 'permission_group_id' => $householdGroup->id]);

        $economyGroup = PermissionGroup::create(['name' => 'Economía']);
        Permission::create(['name' => 'view personal economy', 'route_name' => 'economy.me.index', 'description' => 'Ver economía personal', 'permission_group_id' => $economyGroup->id]);

        $this->role = Role::create(['name' => 'user', 'slug' => 'user']);
        $this->role->syncPermissions(Permission::all());
    }

    public function test_guests_are_redirected_to_the_login_page(): void
    {
        $response = $this->get(route('dashboard'));
        $response->assertRedirect(route('login'));
    }

    public function test_dashboard_renders_the_active_household_without_redirecting(): void
    {
        $user = User::factory()->create();
        $this->createHouseholdForUser($user);

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page): Assert => $page
            ->component('households/Dashboard')
            ->where('household.id', $this->household->id)
        );
    }

    public function test_household_dashboard_renders_correctly(): void
    {
        $user = User::factory()->create();
        $this->createHouseholdForUser($user);

        $response = $this->actingAs($user)->get(route('dashboard'));
        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('households/Dashboard')
            ->has('household')
            ->has('can')
            ->loadDeferredProps(fn (Assert $deferred): Assert => $deferred
                ->has('dashboard')
            )
        );
    }

    public function test_household_dashboard_exposes_module_capabilities(): void
    {
        // The list capability needs both the permission and the household module.
        Permission::create([
            'name' => 'view lists',
            'route_name' => 'households.lists.index',
            'description' => 'Ver listas',
            'permission_group_id' => Permission::first()->permission_group_id,
        ]);
        $this->role->syncPermissions(Permission::all());

        $user = User::factory()->create();
        $user->assignRole($this->role);
        $this->createHouseholdForUser($user);

        HouseholdModule::where('household_id', $this->household->id)->delete();
        HouseholdModule::factory()
            ->forModule(HouseholdModuleEnum::ShoppingLists)
            ->enabled()
            ->create(['household_id' => $this->household->id]);

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk()->assertInertia(fn (Assert $page): Assert => $page
            ->where('can.lists', true)
            ->where('can.recipes', false)
            ->where('can.householdEconomy', false)
            ->loadDeferredProps(fn (Assert $deferred): Assert => $deferred
                ->where('dashboard.enabled_modules', ['shopping_lists'])
            )
        );
    }

    public function test_personal_dashboard_defers_accounts_and_recent_transactions(): void
    {
        $user = User::factory()->create();
        $user->assignRole($this->role);

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk()->assertInertia(fn (Assert $page): Assert => $page
            ->component('households/Dashboard')
            ->where('dashboard', null)
            ->where('household', null)
            ->loadDeferredProps(fn (Assert $deferred): Assert => $deferred
                ->has('personalEconomy')
                ->has('personalAccounts')
                ->has('recentTransactions')
                ->has('pendingImports')
            )
        );
    }

    public function test_dashboard_shows_correct_pending_items_count(): void
    {
        $user = User::factory()->create();
        $this->createHouseholdForUser($user);

        $list = ShoppingList::factory()->create([
            'household_id' => $this->household->id,
            'created_by' => $user->id,
        ]);

        ListItem::factory()->count(3)->unchecked()->create(['list_id' => $list->id]);
        ListItem::factory()->count(2)->checked()->create(['list_id' => $list->id]);

        $response = $this->actingAs($user)->get(route('dashboard'));
        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->loadDeferredProps(fn (Assert $deferred): Assert => $deferred
                ->where('dashboard.pending_items_count', 3)
                ->where('dashboard.active_lists_count', 1)
            )
        );
    }

    public function test_list_counts_include_the_user_private_lists(): void
    {
        $user = User::factory()->create();
        $this->createHouseholdForUser($user);

        $householdList = ShoppingList::factory()->create([
            'household_id' => $this->household->id,
            'created_by' => $user->id,
        ]);
        ListItem::factory()->count(2)->unchecked()->create(['list_id' => $householdList->id]);

        $privateList = ShoppingList::factory()->create([
            'household_id' => null,
            'created_by' => $user->id,
        ]);
        ListItem::factory()->count(3)->unchecked()->create(['list_id' => $privateList->id]);

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk()->assertInertia(fn (Assert $page): Assert => $page
            ->loadDeferredProps(fn (Assert $deferred): Assert => $deferred
                // 2 household + 3 private pending items across 2 active lists.
                ->where('dashboard.pending_items_count', 5)
                ->where('dashboard.active_lists_count', 2)
            )
        );
    }

    public function test_dashboard_shows_enabled_modules(): void
    {
        $user = User::factory()->create();
        $this->createHouseholdForUser($user);

        // Clear any modules created by the household factory
        HouseholdModule::where('household_id', $this->household->id)->delete();

        HouseholdModule::factory()
            ->forModule(HouseholdModuleEnum::ShoppingLists)
            ->enabled()
            ->create(['household_id' => $this->household->id]);

        HouseholdModule::factory()
            ->forModule(HouseholdModuleEnum::Recipes)
            ->enabled()
            ->create(['household_id' => $this->household->id]);

        $response = $this->actingAs($user)->get(route('dashboard'));
        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->loadDeferredProps(fn (Assert $deferred): Assert => $deferred
                ->where('dashboard.enabled_modules', ['recipes', 'shopping_lists'])
            )
        );
    }

    public function test_dashboard_renders_personal_data_for_user_without_household(): void
    {
        $user = User::factory()->create();
        $user->assignRole($this->role);

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk()->assertInertia(fn (Assert $page): Assert => $page
            ->component('households/Dashboard')
            ->where('dashboard', null)
            ->where('household', null)
            ->loadDeferredProps(fn (Assert $deferred): Assert => $deferred
                ->where('personalEconomy.totals.personal_total_minor', 0)
                ->where('personalEconomy.by_household', [])
            )
        );
    }

    public function test_dashboard_redirects_to_household_selection_when_user_has_no_active_household(): void
    {
        $user = User::factory()->create();
        $this->createHouseholdForUser($user);
        $user->update(['active_household_id' => null]);

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertRedirect(route('households.select'));
    }

    public function test_dashboard_is_personal_when_households_are_disabled(): void
    {
        $user = User::factory()->create();
        $this->createHouseholdForUser($user);
        $user->update(['households_enabled' => false]);

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk()->assertInertia(fn (Assert $page): Assert => $page
            ->component('households/Dashboard')
            ->where('dashboard', null)
            ->where('household', null)
            ->where('householdContext.active', null)
            ->where('householdContext.households', [])
        );
    }

    public function test_dashboard_clears_invalid_active_household_and_renders_personal_dashboard(): void
    {
        $user = User::factory()->create();
        $this->createHouseholdForUser($user);
        $user->householdMemberships()->delete();

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk()->assertInertia(fn (Assert $page): Assert => $page
            ->component('households/Dashboard')
            ->where('dashboard', null)
            ->where('household', null)
        );
        $this->assertNull($user->fresh()->active_household_id);
    }
}
