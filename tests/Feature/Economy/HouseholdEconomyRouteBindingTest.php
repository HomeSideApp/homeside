<?php

namespace Tests\Feature\Economy;

use App\Models\EconomicTransaction;
use App\Models\Household;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\WithHousehold;
use Tests\TestCase;

/**
 * Regression tests for implicit route binding on the household-scoped economy
 * routes (households/{household}/economy/{transaction}/...).
 *
 * The extra {household} route parameter must not shift positional arguments
 * when the controller only type-hints EconomicTransaction: without a
 * Household parameter declared before it, Laravel passed the household string
 * into $transaction and the action threw a TypeError.
 */
final class HouseholdEconomyRouteBindingTest extends TestCase
{
    use RefreshDatabase;
    use WithHousehold;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_household_scoped_edit_page_resolves_transaction_binding(): void
    {
        [$user, $household, $transaction] = $this->seedHouseholdTransaction();

        $response = $this->actingAs($user)->get(
            route('households.economy.edit', ['household' => $household, 'transaction' => $transaction])
        );

        $response->assertOk()->assertInertia(fn (Assert $page): Assert => $page
            ->component('economy/Edit')
            ->where('household.id', $household->id)
            ->where('transaction.id', $transaction->id)
        );
    }

    public function test_household_scoped_show_page_resolves_transaction_binding(): void
    {
        [$user, $household, $transaction] = $this->seedHouseholdTransaction();

        $response = $this->actingAs($user)->get(
            route('households.economy.show', ['household' => $household, 'transaction' => $transaction])
        );

        $response->assertOk()->assertInertia(fn (Assert $page): Assert => $page
            ->component('economy/Show')
            ->where('household.id', $household->id)
            ->where('transaction.id', $transaction->id)
        );
    }

    public function test_household_scoped_update_redirects_to_transaction_show(): void
    {
        [$user, $household, $transaction] = $this->seedHouseholdTransaction();

        $response = $this->actingAs($user)->put(
            route('households.economy.update', ['household' => $household, 'transaction' => $transaction]),
            [
                'type' => $transaction->type->value,
                'scope' => 'personal',
                'title' => 'Título actualizado',
                'amount' => '12.34',
                'currency' => $transaction->currency,
                'occurred_at' => now()->toDateString(),
            ]
        );

        $response->assertRedirect(
            route('households.economy.show', ['household' => $household, 'transaction' => $transaction])
        );

        $this->assertDatabaseHas('economic_transactions', [
            'id' => $transaction->id,
            'title' => 'Título actualizado',
        ]);
    }

    public function test_household_scoped_destroy_removes_transaction(): void
    {
        [$user, $household, $transaction] = $this->seedHouseholdTransaction();

        $response = $this->actingAs($user)->delete(
            route('households.economy.destroy', ['household' => $household, 'transaction' => $transaction])
        );

        $response->assertRedirect(route('households.economy.index', $household));

        $this->assertDatabaseMissing('economic_transactions', ['id' => $transaction->id]);
    }

    /**
     * Create a user with a household and one shared transaction owned by them.
     *
     * @return array{0: User, 1: Household, 2: EconomicTransaction}
     */
    private function seedHouseholdTransaction(): array
    {
        $user = User::factory()->create();
        $user->assignRole('user');
        $household = $this->createHouseholdForUser($user);

        $transaction = EconomicTransaction::factory()
            ->forHousehold($household)
            ->createdBy($user)
            ->create();

        return [$user, $household, $transaction];
    }
}
