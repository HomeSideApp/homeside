<?php

namespace Tests\Feature;

use App\Actions\Economy\GetHouseholdEconomyOverview;
use App\Actions\Economy\GetHouseholdsComparison;
use App\Actions\Economy\GetPersonalEconomyOverview;
use App\Enums\HouseholdModule as HouseholdModuleEnum;
use App\Enums\SplitType;
use App\Models\EconomicTransaction;
use App\Models\EconomicTransactionParticipant;
use App\Models\Household;
use App\Models\HouseholdMember;
use App\Models\HouseholdModule;
use App\Models\Permission;
use App\Models\PermissionGroup;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\WithHousehold;
use Tests\TestCase;

final class EconomyOverviewTest extends TestCase
{
    use RefreshDatabase, WithHousehold;

    /**
     * Create a household membership row for the given user.
     */
    private function addMember(Household $household, User $user): HouseholdMember
    {
        return HouseholdMember::create([
            'user_id' => $user->id,
            'household_id' => $household->id,
            'role' => 'member',
            'joined_at' => now(),
        ]);
    }

    /**
     * Verify the household overview totals only count the current month.
     *
     * @return void This test method does not return a value.
     */
    public function test_household_overview_totals_use_the_current_month(): void
    {
        $user = User::factory()->create();
        $household = $this->createHouseholdForUser($user);

        EconomicTransaction::factory()->forHousehold($household)->createdBy($user)->shared()->create([
            'amount_minor' => 5000,
            'occurred_at' => Carbon::now(),
        ]);
        EconomicTransaction::factory()->forHousehold($household)->createdBy($user)->income()->create([
            'amount_minor' => 30000,
            'occurred_at' => Carbon::now(),
        ]);
        EconomicTransaction::factory()->forHousehold($household)->createdBy($user)->shared()->create([
            'amount_minor' => 9900,
            'occurred_at' => Carbon::now()->subMonths(5),
        ]);

        $overview = (new GetHouseholdEconomyOverview)->execute($household, $user);

        $this->assertSame('EUR', $overview['currency']);
        $this->assertSame(5000, $overview['totals']['expenses_minor']);
        $this->assertSame(30000, $overview['totals']['income_minor']);
        $this->assertSame(25000, $overview['totals']['balance_minor']);
        $this->assertSame(0, $overview['totals']['personal_expenses_minor']);
        $this->assertSame(5000, $overview['totals']['shared_expenses_minor']);
    }

    /**
     * Verify the household overview excludes other members' personal transactions.
     *
     * @return void This test method does not return a value.
     */
    public function test_household_overview_hides_other_members_personal_transactions(): void
    {
        $user = User::factory()->create();
        $household = $this->createHouseholdForUser($user);
        $mate = User::factory()->create();
        $this->addMember($household, $mate);

        EconomicTransaction::factory()->forHousehold($household)->createdBy($user)->shared()->create([
            'amount_minor' => 4000,
            'place' => 'Mercadona',
            'occurred_at' => Carbon::now(),
        ]);
        EconomicTransaction::factory()->forHousehold($household)->createdBy($mate)->create([
            'amount_minor' => 7000,
            'place' => 'Oculto',
            'occurred_at' => Carbon::now(),
        ]);
        EconomicTransaction::factory()->forHousehold($household)->createdBy($mate)->shared()->create([
            'amount_minor' => 2000,
            'place' => 'Mercadona',
            'occurred_at' => Carbon::now(),
        ]);

        $overview = (new GetHouseholdEconomyOverview)->execute($household, $user);

        $this->assertSame(6000, $overview['totals']['expenses_minor']);
        $this->assertSame(['Mercadona'], array_column($overview['by_place'], 'place'));
        $this->assertSame(6000, $overview['by_place'][0]['total_minor']);
    }

    /**
     * Verify the household monthly series is zero-filled across the twelve-month window.
     *
     * @return void This test method does not return a value.
     */
    public function test_household_monthly_series_is_zero_filled_for_twelve_months(): void
    {
        $user = User::factory()->create();
        $household = $this->createHouseholdForUser($user);

        EconomicTransaction::factory()->forHousehold($household)->createdBy($user)->shared()->create([
            'amount_minor' => 1500,
            'occurred_at' => Carbon::now()->subMonths(11),
        ]);

        // Outside the window: must not appear in the series at all.
        EconomicTransaction::factory()->forHousehold($household)->createdBy($user)->shared()->create([
            'amount_minor' => 8800,
            'occurred_at' => Carbon::now()->subMonths(12),
        ]);

        $overview = (new GetHouseholdEconomyOverview)->execute($household, $user);
        $months = array_column($overview['monthly'], 'month');

        $this->assertCount(12, $overview['monthly']);
        $this->assertSame(Carbon::now()->subMonths(11)->format('Y-m'), $months[0]);
        $this->assertSame(Carbon::now()->format('Y-m'), $months[11]);
        $this->assertSame(1500, $overview['monthly'][0]['expenses_minor']);
        $this->assertSame(0, $overview['monthly'][1]['expenses_minor']);
        $this->assertSame(0, $overview['monthly'][11]['expenses_minor']);
        $this->assertNotContains(Carbon::now()->subMonths(12)->format('Y-m'), $months);
    }

    /**
     * Verify the household place ranking keeps only the top five places.
     *
     * @return void This test method does not return a value.
     */
    public function test_household_by_place_is_limited_to_the_top_five(): void
    {
        $user = User::factory()->create();
        $household = $this->createHouseholdForUser($user);

        foreach (['A' => 100, 'B' => 200, 'C' => 300, 'D' => 400, 'E' => 500, 'F' => 600] as $place => $amount) {
            EconomicTransaction::factory()->forHousehold($household)->createdBy($user)->shared()->create([
                'amount_minor' => $amount,
                'place' => $place,
                'occurred_at' => Carbon::now(),
            ]);
        }

        $overview = (new GetHouseholdEconomyOverview)->execute($household, $user);

        $this->assertSame(['F', 'E', 'D', 'C', 'B'], array_column($overview['by_place'], 'place'));
        $this->assertSame(600, $overview['by_place'][0]['total_minor']);
    }

    /**
     * Verify the household overview reports member spending with names.
     *
     * @return void This test method does not return a value.
     */
    public function test_household_by_member_groups_expenses_per_creator(): void
    {
        $user = User::factory()->create();
        $household = $this->createHouseholdForUser($user);
        $mate = User::factory()->create(['name' => 'Ana Mate']);
        $this->addMember($household, $mate);

        EconomicTransaction::factory()->forHousehold($household)->createdBy($user)->shared()->create([
            'amount_minor' => 1000,
            'occurred_at' => Carbon::now(),
        ]);
        EconomicTransaction::factory()->forHousehold($household)->createdBy($mate)->shared()->create([
            'amount_minor' => 2500,
            'occurred_at' => Carbon::now(),
        ]);

        $overview = (new GetHouseholdEconomyOverview)->execute($household, $user);

        $this->assertSame([$mate->id, $user->id], array_column($overview['by_member'], 'creator_id'));
        $this->assertSame('Ana Mate', $overview['by_member'][0]['name']);
        $this->assertSame(2500, $overview['by_member'][0]['total_minor']);
        $this->assertSame($user->name, $overview['by_member'][1]['name']);
        $this->assertSame(1000, $overview['by_member'][1]['total_minor']);
    }

    /**
     * Verify the household overview selects the dominant currency and reports the rest.
     *
     * @return void This test method does not return a value.
     */
    public function test_household_overview_separates_currencies(): void
    {
        $user = User::factory()->create();
        $household = $this->createHouseholdForUser($user);

        EconomicTransaction::factory()->forHousehold($household)->createdBy($user)->shared()->create([
            'amount_minor' => 1200,
            'currency' => 'EUR',
            'occurred_at' => Carbon::now(),
        ]);
        EconomicTransaction::factory()->forHousehold($household)->createdBy($user)->shared()->create([
            'amount_minor' => 9000,
            'currency' => 'USD',
            'occurred_at' => Carbon::now(),
        ]);

        $overview = (new GetHouseholdEconomyOverview)->execute($household, $user);

        $this->assertSame('USD', $overview['currency']);
        $this->assertSame(9000, $overview['totals']['expenses_minor']);
        $this->assertSame(['EUR' => 1200], $overview['other_currencies']);
    }

    /**
     * Verify the personal overview combines own expenses and shared participation.
     *
     * @return void This test method does not return a value.
     */
    public function test_personal_overview_combines_own_and_shared_participation(): void
    {
        $user = User::factory()->create();
        $this->createHouseholdForUser($user);
        $member = $this->household->members()->first();
        $mate = User::factory()->create();
        $mateMember = $this->addMember($this->household, $mate);

        // Own household expense plus a private expense outside any household.
        EconomicTransaction::factory()->forHousehold($this->household)->createdBy($user)->create([
            'amount_minor' => 3000,
            'occurred_at' => Carbon::now(),
        ]);
        EconomicTransaction::factory()->private()->createdBy($user)->create([
            'amount_minor' => 1500,
            'occurred_at' => Carbon::now(),
        ]);

        // Shared expense created by the mate, in which the user participates.
        $shared = EconomicTransaction::factory()->forHousehold($this->household)->createdBy($mate)->shared()->create([
            'amount_minor' => 10000,
            'occurred_at' => Carbon::now(),
        ]);
        EconomicTransactionParticipant::create([
            'transaction_id' => $shared->id,
            'household_member_id' => $member->id,
            'split_type' => SplitType::Fixed,
            'amount_minor' => 4000,
        ]);
        EconomicTransactionParticipant::create([
            'transaction_id' => $shared->id,
            'household_member_id' => $mateMember->id,
            'split_type' => SplitType::Fixed,
            'amount_minor' => 6000,
        ]);

        $overview = (new GetPersonalEconomyOverview)->execute($user);

        $this->assertSame(4500, $overview['totals']['own_minor']);
        $this->assertSame(1500, $overview['totals']['without_house_minor']);
        $this->assertSame(4000, $overview['totals']['shared_participation_minor']);
        $this->assertSame(8500, $overview['totals']['personal_total_minor']);
        $this->assertSame(4500, $overview['monthly'][11]['own_minor']);
        $this->assertSame(4000, $overview['monthly'][11]['shared_minor']);
    }

    /**
     * Verify the personal breakdown labels household-less expenses with a null name.
     *
     * @return void This test method does not return a value.
     */
    public function test_personal_by_household_includes_privates_as_null_name(): void
    {
        $user = User::factory()->create();
        $this->createHouseholdForUser($user);

        EconomicTransaction::factory()->forHousehold($this->household)->createdBy($user)->create([
            'amount_minor' => 2000,
            'occurred_at' => Carbon::now(),
        ]);
        EconomicTransaction::factory()->private()->createdBy($user)->create([
            'amount_minor' => 500,
            'occurred_at' => Carbon::now(),
        ]);

        $overview = (new GetPersonalEconomyOverview)->execute($user);

        $this->assertSame([$this->household->id, null], array_column($overview['by_household'], 'household_id'));
        $this->assertSame($this->household->name, $overview['by_household'][0]['name']);
        $this->assertNull($overview['by_household'][1]['name']);
        $this->assertSame(2000, $overview['by_household'][0]['total_minor']);
        $this->assertSame(500, $overview['by_household'][1]['total_minor']);
    }

    /**
     * Verify expenses without an occurrence date are still counted using their creation date.
     *
     * Imported tickets may have no readable date, so the dashboard must fall back to `created_at`
     * instead of dropping the transaction from every total and series.
     *
     * @return void This test method does not return a value.
     */
    public function test_personal_overview_counts_expenses_without_occurrence_date(): void
    {
        $user = User::factory()->create();
        $this->createHouseholdForUser($user);

        EconomicTransaction::factory()->private()->createdBy($user)->create([
            'amount_minor' => 620,
            'occurred_at' => null,
            'created_at' => Carbon::now(),
        ]);

        $overview = (new GetPersonalEconomyOverview)->execute($user);

        $this->assertSame('EUR', $overview['currency']);
        $this->assertSame(620, $overview['totals']['own_minor']);
        $this->assertSame(620, $overview['totals']['without_house_minor']);
        $this->assertSame(620, $overview['totals']['personal_total_minor']);
        $this->assertSame(620, $overview['monthly'][11]['own_minor']);
    }

    /**
     * Verify an expense older than the window falls back to its creation date when undated.
     *
     * @return void This test method does not return a value.
     */
    public function test_personal_overview_uses_creation_date_for_undated_old_expenses(): void
    {
        $user = User::factory()->create();
        $this->createHouseholdForUser($user);

        // Undated, so the fallback decides: created long ago, therefore outside the window.
        EconomicTransaction::factory()->private()->createdBy($user)->create([
            'amount_minor' => 999,
            'occurred_at' => null,
            'created_at' => Carbon::now()->subMonths(18),
        ]);

        $overview = (new GetPersonalEconomyOverview)->execute($user);

        $this->assertSame(0, $overview['totals']['own_minor']);
    }

    /**
     * Verify households without the economy module do not feed the personal overview.
     *
     * A household that turned the module off must stop contributing to the personal figures, both
     * through the user's own expenses there and through shared participations.
     *
     * @return void This test method does not return a value.
     */
    public function test_personal_overview_excludes_households_without_economy_module(): void
    {
        $user = User::factory()->create();
        $this->createHouseholdForUser($user);

        HouseholdModule::where('household_id', $this->household->id)->delete();

        EconomicTransaction::factory()->forHousehold($this->household)->createdBy($user)->create([
            'amount_minor' => 3300,
            'occurred_at' => Carbon::now(),
        ]);

        $overview = (new GetPersonalEconomyOverview)->execute($user);

        $this->assertSame(0, $overview['totals']['own_minor']);
        $this->assertSame([], $overview['by_household']);
    }

    /**
     * Verify the comparison separates own spending from shared participation per destination and
     * reports income plus the resulting net balance used by the cross-scope "this month" KPIs.
     *
     * @return void This test method does not return a value.
     */
    public function test_comparison_separates_own_and_shared_per_destination(): void
    {
        $user = User::factory()->create();
        $this->createHouseholdForUser($user);
        $member = $this->household->members()->first();
        $mate = User::factory()->create();
        $mateMember = $this->addMember($this->household, $mate);

        HouseholdModule::where('household_id', $this->household->id)->delete();
        HouseholdModule::factory()
            ->forModule(HouseholdModuleEnum::Economy)
            ->enabled()
            ->create(['household_id' => $this->household->id]);

        EconomicTransaction::factory()->forHousehold($this->household)->createdBy($user)->create([
            'amount_minor' => 1000,
            'occurred_at' => Carbon::now(),
        ]);
        EconomicTransaction::factory()->private()->createdBy($user)->create([
            'amount_minor' => 500,
            'occurred_at' => Carbon::now(),
        ]);
        EconomicTransaction::factory()->forHousehold($this->household)->createdBy($user)->income()->create([
            'amount_minor' => 700,
            'occurred_at' => Carbon::now(),
        ]);
        EconomicTransaction::factory()->private()->createdBy($user)->income()->create([
            'amount_minor' => 300,
            'occurred_at' => Carbon::now(),
        ]);
        // Shared income belongs to the household, not to one participant, so it must stay out.
        EconomicTransaction::factory()->forHousehold($this->household)->createdBy($mate)->shared()->income()->create([
            'amount_minor' => 900,
            'occurred_at' => Carbon::now(),
        ]);

        $shared = EconomicTransaction::factory()->forHousehold($this->household)->createdBy($mate)->shared()->create([
            'amount_minor' => 4000,
            'occurred_at' => Carbon::now(),
        ]);
        EconomicTransactionParticipant::create([
            'transaction_id' => $shared->id,
            'household_member_id' => $member->id,
            'split_type' => SplitType::Fixed,
            'amount_minor' => 1500,
        ]);
        EconomicTransactionParticipant::create([
            'transaction_id' => $shared->id,
            'household_member_id' => $mateMember->id,
            'split_type' => SplitType::Fixed,
            'amount_minor' => 2500,
        ]);

        $comparison = (new GetHouseholdsComparison)->execute($user);
        $byScope = collect($comparison['rows'])->keyBy('scope');

        $this->assertSame(1000, $byScope['household']['own_minor']);
        $this->assertSame(1500, $byScope['household']['shared_participation_minor']);
        $this->assertSame(2500, $byScope['household']['balance_minor']);
        $this->assertSame(700, $byScope['household']['income_minor']);
        $this->assertSame(-1800, $byScope['household']['net_minor']);
        $this->assertSame($this->household->name, $byScope['household']['name']);

        $this->assertSame(500, $byScope['private']['own_minor']);
        $this->assertSame(0, $byScope['private']['shared_participation_minor']);
        $this->assertSame(300, $byScope['private']['income_minor']);
        $this->assertSame(-200, $byScope['private']['net_minor']);
        $this->assertNull($byScope['private']['household_id']);

        $this->assertSame(1500, $comparison['totals']['own_minor']);
        $this->assertSame(1500, $comparison['totals']['shared_participation_minor']);
        $this->assertSame(3000, $comparison['totals']['balance_minor']);
        $this->assertSame(1000, $comparison['totals']['income_minor']);
        $this->assertSame(-2000, $comparison['totals']['net_minor']);
    }

    /**
     * Verify the comparison skips households that disabled the economy module.
     *
     * @return void This test method does not return a value.
     */
    public function test_comparison_excludes_households_without_economy_module(): void
    {
        $user = User::factory()->create();
        $this->createHouseholdForUser($user);

        HouseholdModule::where('household_id', $this->household->id)->delete();

        EconomicTransaction::factory()->forHousehold($this->household)->createdBy($user)->create([
            'amount_minor' => 3300,
            'occurred_at' => Carbon::now(),
        ]);

        $comparison = (new GetHouseholdsComparison)->execute($user);

        $this->assertSame([], $comparison['rows']);
        $this->assertSame(0, $comparison['totals']['balance_minor']);
    }

    /**
     * Verify the personal overview excludes households the user no longer belongs to.
     *
     * @return void This test method does not return a value.
     */
    public function test_personal_overview_excludes_abandoned_households(): void
    {
        $user = User::factory()->create();
        $old = Household::factory()->create(['name' => 'Casa antigua', 'created_by' => $user->id]);

        // The user created the expense there and later left the household entirely, so its data
        // must not resurface in the personal overview.
        EconomicTransaction::factory()->forHousehold($old)->createdBy($user)->create([
            'amount_minor' => 4200,
            'occurred_at' => Carbon::now(),
        ]);

        $overview = (new GetPersonalEconomyOverview)->execute($user);

        $this->assertSame(0, $overview['totals']['own_minor']);
        $this->assertSame([], $overview['by_household']);
    }

    /**
     * Verify the personal overview is cross-household and excludes foreign households.
     *
     * @return void This test method does not return a value.
     */
    public function test_personal_overview_is_cross_household(): void
    {
        $user = User::factory()->create();
        $this->createHouseholdForUser($user);
        $second = Household::factory()->create(['name' => 'Casa secundaria', 'created_by' => $user->id]);
        $this->addMember($second, $user);

        EconomicTransaction::factory()->forHousehold($second)->createdBy($user)->create([
            'amount_minor' => 1100,
            'occurred_at' => Carbon::now(),
        ]);

        // A foreign household the user does not belong to.
        $foreignUser = User::factory()->create();
        $foreignHousehold = $this->createHouseholdForUser($foreignUser);
        EconomicTransaction::factory()->forHousehold($foreignHousehold)->createdBy($foreignUser)->create([
            'amount_minor' => 777,
            'occurred_at' => Carbon::now(),
        ]);

        $overview = (new GetPersonalEconomyOverview)->execute($user);

        $this->assertSame(1100, $overview['totals']['own_minor']);
        $this->assertCount(1, $overview['by_household']);
        $this->assertSame('Casa secundaria', $overview['by_household'][0]['name']);
        $this->assertSame(1100, $overview['by_household'][0]['total_minor']);
    }

    /**
     * Verify the dashboard defers the economy props when module and permission allow them.
     *
     * @return void This test method does not return a value.
     */
    public function test_dashboard_defers_household_economy_when_module_enabled(): void
    {
        $user = User::factory()->create();
        $this->seedDashboardPermissions($user);
        $this->createHouseholdForUser($user);

        HouseholdModule::where('household_id', $this->household->id)->delete();
        HouseholdModule::factory()
            ->forModule(HouseholdModuleEnum::Economy)
            ->enabled()
            ->create(['household_id' => $this->household->id]);

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk()->assertInertia(fn (Assert $page): Assert => $page
            ->component('households/Dashboard')
            ->loadDeferredProps(fn (Assert $deferred): Assert => $deferred
                ->where('householdEconomy.currency', null)
                ->where('householdEconomy.totals.expenses_minor', 0)
                ->has('householdEconomy.monthly', 12)
                ->where('householdEconomy.by_place', [])
                ->where('householdEconomy.by_member', [])
                ->where('personalEconomy.totals.personal_total_minor', 0)
            )
        );
    }

    /**
     * Verify the dashboard omits the household economy prop when the module is disabled.
     *
     * @return void This test method does not return a value.
     */
    public function test_dashboard_omits_household_economy_without_module(): void
    {
        $user = User::factory()->create();
        $this->seedDashboardPermissions($user);
        $this->createHouseholdForUser($user);

        HouseholdModule::where('household_id', $this->household->id)->delete();
        HouseholdModule::factory()
            ->forModule(HouseholdModuleEnum::ShoppingLists)
            ->enabled()
            ->create(['household_id' => $this->household->id]);

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk()->assertInertia(fn (Assert $page): Assert => $page
            ->loadDeferredProps(fn (Assert $deferred): Assert => $deferred
                ->missing('householdEconomy')
                ->has('personalEconomy')
            )
        );
    }

    /**
     * Verify the dashboard omits the personal economy prop without the personal permission.
     *
     * @return void This test method does not return a value.
     */
    public function test_dashboard_omits_personal_economy_without_permission(): void
    {
        $user = User::factory()->create();
        $this->seedDashboardPermissions($user, includePersonalEconomy: false);
        $this->createHouseholdForUser($user);

        HouseholdModule::where('household_id', $this->household->id)->delete();
        HouseholdModule::factory()
            ->forModule(HouseholdModuleEnum::Economy)
            ->enabled()
            ->create(['household_id' => $this->household->id]);

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk()->assertInertia(fn (Assert $page): Assert => $page
            ->loadDeferredProps(fn (Assert $deferred): Assert => $deferred
                ->has('householdEconomy')
                ->missing('personalEconomy')
            )
        );
    }

    /**
     * Seed the permission set required to render the household dashboard.
     *
     * @param  User  $user  The user the generated role is assigned to.
     * @param  bool  $includePersonalEconomy  Whether the personal economy permission should be granted.
     * @return void This setup method does not return a value.
     */
    private function seedDashboardPermissions(User $user, bool $includePersonalEconomy = true): void
    {
        $group = PermissionGroup::create(['name' => 'Dashboard']);
        Permission::create(['name' => 'view dashboard', 'route_name' => 'dashboard', 'description' => 'Ver dashboard', 'permission_group_id' => $group->id]);

        $householdGroup = PermissionGroup::create(['name' => 'Hogares']);
        foreach ([
            'households.index' => 'view households',
            'households.show' => 'view household',
            'households.switch' => 'switch household',
        ] as $routeName => $name) {
            Permission::create(['name' => $name, 'route_name' => $routeName, 'description' => $name, 'permission_group_id' => $householdGroup->id]);
        }

        Permission::create([
            'name' => 'view household economy',
            'route_name' => 'households.economy.index',
            'description' => 'Ver economía del hogar',
            'permission_group_id' => $householdGroup->id,
        ]);

        if ($includePersonalEconomy) {
            Permission::create([
                'name' => 'view personal economy',
                'route_name' => 'economy.me.index',
                'description' => 'Ver resumen de cuenta privada',
                'permission_group_id' => $householdGroup->id,
            ]);
        }

        $role = Role::create(['name' => 'user', 'slug' => 'user']);
        $role->syncPermissions(Permission::all());
        $user->assignRole($role);
    }
}
