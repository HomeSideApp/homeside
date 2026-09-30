<?php

namespace Tests\Feature\Economy;

use App\Actions\Economy\GetEconomyStats;
use App\Enums\HouseholdRole;
use App\Enums\TransactionScope;
use App\Models\EconomicTransaction;
use App\Models\EconomicTransactionParticipant;
use App\Models\Household;
use App\Models\HouseholdMember;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class EconomyTransactionApiTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Household $household;

    /**
     * Prepare an authenticated household administrator for each transaction test.
     *
     * @return void This setup method does not return a value.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->household = Household::factory()->create();
        HouseholdMember::factory()->create([
            'household_id' => $this->household->id,
            'user_id' => $this->user->id,
            'role' => HouseholdRole::Admin,
        ]);

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->user->assignRole('user');

        Sanctum::actingAs($this->user);
    }

    /**
     * Verify that a user can create an expense transaction.
     *
     * @return void This test method does not return a value.
     */
    public function test_user_can_create_expense(): void
    {
        $response = $this->postJson("/api/v1/households/{$this->household->id}/economy/transactions", [
            'type' => 'expense',
            'scope' => 'personal',
            'title' => 'Compra semanal',
            'amount_minor' => 4273,
            'currency' => 'EUR',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.type', 'expense')
            ->assertJsonPath('data.amount_minor', 4273)
            ->assertJsonPath('data.currency', 'EUR');

        $this->assertDatabaseHas('economic_transactions', [
            'household_id' => $this->household->id,
            'type' => 'expense',
            'amount_minor' => 4273,
        ]);
    }

    /**
     * Verify that a user can create a shared income transaction.
     *
     * @return void This test method does not return a value.
     */
    public function test_user_can_create_income(): void
    {
        $response = $this->postJson("/api/v1/households/{$this->household->id}/economy/transactions", [
            'type' => 'income',
            'scope' => 'shared',
            'title' => 'Salario compartido',
            'amount_minor' => 100000,
            'currency' => 'EUR',
            'participants' => [
                ['household_member_id' => $this->user->householdMemberships()->first()->id, 'split_type' => 'equal'],
            ],
        ]);

        $response->assertCreated()->assertJsonPath('data.type', 'income');
    }

    /**
     * Verify that a user can create a private transaction without a household.
     *
     * @return void This test method does not return a value.
     */
    public function test_user_can_create_private_transaction_without_household(): void
    {
        $response = $this->postJson('/api/v1/economy/me/transactions', [
            'type' => 'expense',
            'scope' => 'personal',
            'title' => 'Gasto privado',
            'amount_minor' => 1050,
            'currency' => 'EUR',
        ]);

        $response->assertCreated();

        $this->assertDatabaseHas('economic_transactions', [
            'created_by' => $this->user->id,
            'household_id' => null,
            'scope' => 'personal',
            'amount_minor' => 1050,
        ]);
    }

    /**
     * Verify that an empty participant collection from the private form is ignored.
     *
     * @return void This test method does not return a value.
     */
    public function test_private_transaction_accepts_empty_participants_from_the_form(): void
    {
        $this->postJson('/api/v1/economy/me/transactions', [
            'type' => 'expense',
            'scope' => 'personal',
            'title' => 'Private expense',
            'amount_minor' => 1050,
            'currency' => 'EUR',
            'participants' => [],
        ])->assertCreated();
    }

    /**
     * Verify that private transactions retain a null household and personal scope.
     *
     * @return void This test method does not return a value.
     */
    public function test_private_transaction_has_null_household_and_personal_scope(): void
    {
        EconomicTransaction::factory()->private()->createdBy($this->user)->create();

        $transaction = EconomicTransaction::query()->whereNull('household_id')->first();

        $this->assertNotNull($transaction);
        $this->assertSame($this->user->id, $transaction->created_by);
        $this->assertSame(TransactionScope::Personal, $transaction->scope);
    }

    /**
     * Verify that a user cannot view another user's private transaction.
     *
     * @return void This test method does not return a value.
     */
    public function test_user_cannot_see_private_transaction_of_other_user(): void
    {
        $other = User::factory()->create();
        $private = EconomicTransaction::factory()->private()->createdBy($other)->create();

        $this->getJson("/api/v1/economy/me/transactions/{$private->id}")
            ->assertForbidden();
    }

    /**
     * Verify that household membership does not expose another user's private transaction.
     *
     * @return void This test method does not return a value.
     */
    public function test_user_in_another_household_cannot_access_my_private_transaction(): void
    {
        $otherHousehold = Household::factory()->create();
        HouseholdMember::factory()->create([
            'household_id' => $otherHousehold->id,
            'user_id' => $this->user->id,
        ]);

        $other = User::factory()->create();
        $private = EconomicTransaction::factory()->private()->createdBy($other)->create();

        $this->getJson("/api/v1/households/{$otherHousehold->id}/economy/transactions/{$private->id}")
            ->assertForbidden();
    }

    /**
     * Verify that a user cannot view transactions belonging to an unrelated household.
     *
     * @return void This test method does not return a value.
     */
    public function test_user_cannot_see_other_household_transactions(): void
    {
        $other = User::factory()->create();
        $otherHousehold = Household::factory()->create();
        HouseholdMember::factory()->create([
            'household_id' => $otherHousehold->id,
            'user_id' => $other->id,
        ]);
        EconomicTransaction::factory()->forHousehold($otherHousehold)->createdBy($other)->shared()->create();

        $this->getJson("/api/v1/households/{$otherHousehold->id}/economy/transactions")
            ->assertForbidden();
    }

    /**
     * Verify that a household member can list visible household transactions.
     *
     * @return void This test method does not return a value.
     */
    public function test_user_can_list_transactions(): void
    {
        EconomicTransaction::factory()->count(3)->forHousehold($this->household)->createdBy($this->user)->create();

        $this->getJson("/api/v1/households/{$this->household->id}/economy/transactions")
            ->assertOk()
            ->assertJsonCount(3, 'data');
    }

    public function test_user_can_get_household_totals_available_in_web_overview(): void
    {
        EconomicTransaction::factory()
            ->forHousehold($this->household)
            ->createdBy($this->user)
            ->create(['amount_minor' => 4273]);

        $this->getJson("/api/v1/households/{$this->household->id}/economy/totals")
            ->assertOk()
            ->assertJsonPath('data.expenses_minor', 4273)
            ->assertJsonStructure([
                'data' => [
                    'period',
                    'expenses_minor',
                    'income_minor',
                    'balance_minor',
                    'personal_expenses_minor',
                    'shared_expenses_minor',
                    'by_currency',
                    'by_member',
                    'by_place',
                ],
            ]);
    }

    /**
     * Verify that a transaction owner can update transaction values.
     *
     * @return void This test method does not return a value.
     */
    public function test_user_can_update_transaction(): void
    {
        $transaction = EconomicTransaction::factory()->forHousehold($this->household)->createdBy($this->user)->create();

        $this->patchJson("/api/v1/households/{$this->household->id}/economy/transactions/{$transaction->id}", [
            'title' => 'Título editado',
            'amount_minor' => 9999,
        ])
            ->assertOk()
            ->assertJsonPath('data.title', 'Título editado')
            ->assertJsonPath('data.amount_minor', 9999);
    }

    /**
     * Verify that the API accepts the same PUT update verb used by the web endpoint.
     *
     * @return void This test method does not return a value.
     */
    public function test_user_can_update_transaction_with_put(): void
    {
        $transaction = EconomicTransaction::factory()->forHousehold($this->household)->createdBy($this->user)->create();

        $this->putJson("/api/v1/households/{$this->household->id}/economy/transactions/{$transaction->id}", [
            'title' => 'Actualizada con PUT',
        ])->assertOk()
            ->assertJsonPath('data.title', 'Actualizada con PUT');
    }

    /**
     * Verify that optional transaction fields can be explicitly cleared during an update.
     *
     * @return void This test method does not return a value.
     */
    public function test_user_can_clear_optional_transaction_fields(): void
    {
        $transaction = EconomicTransaction::factory()->forHousehold($this->household)->createdBy($this->user)->create([
            'place' => 'Existing place',
            'notes' => 'Existing notes',
            'occurred_at' => now(),
        ]);

        $this->patchJson("/api/v1/households/{$this->household->id}/economy/transactions/{$transaction->id}", [
            'place' => null,
            'notes' => null,
            'occurred_at' => null,
        ])->assertOk();

        $transaction->refresh();
        $this->assertNull($transaction->place);
        $this->assertNull($transaction->notes);
        $this->assertNull($transaction->occurred_at);
    }

    /**
     * Verify that a transaction owner can delete a transaction.
     *
     * @return void This test method does not return a value.
     */
    public function test_user_can_delete_transaction(): void
    {
        $transaction = EconomicTransaction::factory()->forHousehold($this->household)->createdBy($this->user)->create();

        $this->deleteJson("/api/v1/households/{$this->household->id}/economy/transactions/{$transaction->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('economic_transactions', ['id' => $transaction->id]);
    }

    /**
     * Verify that a private transaction cannot be promoted to shared scope.
     *
     * @return void This test method does not return a value.
     */
    public function test_cannot_promote_private_transaction_to_shared_via_update(): void
    {
        $transaction = EconomicTransaction::factory()->private()->createdBy($this->user)->create();

        $this->patchJson("/api/v1/economy/me/transactions/{$transaction->id}", [
            'scope' => 'shared',
        ])->assertJsonValidationErrors(['scope']);
    }

    /**
     * Verify that personal totals include owned transactions and shared participations.
     *
     * @return void This test method does not return a value.
     */
    public function test_personal_totals_sum_own_private_plus_own_in_households_plus_participations(): void
    {
        $now = now();

        // Private account: 10.00.
        EconomicTransaction::factory()->private()->createdBy($this->user)->create(['amount_minor' => 1000, 'occurred_at' => $now]);
        // Household account: 20.00.
        EconomicTransaction::factory()->forHousehold($this->household)->createdBy($this->user)->create(['amount_minor' => 2000, 'occurred_at' => $now]);
        // Another user's shared transaction: 30.00 with a 10.00 participation.
        $other = User::factory()->create();
        HouseholdMember::factory()->create(['household_id' => $this->household->id, 'user_id' => $other->id]);
        $shared = EconomicTransaction::factory()->forHousehold($this->household)->createdBy($other)->shared()->create(['amount_minor' => 3000, 'occurred_at' => $now]);
        EconomicTransactionParticipant::factory()->create([
            'transaction_id' => $shared->id,
            'household_member_id' => $this->user->householdMemberships()->first()->id,
            'split_type' => 'fixed',
            'amount_minor' => 1000,
        ]);
        $sharedIncome = EconomicTransaction::factory()
            ->forHousehold($this->household)
            ->createdBy($other)
            ->shared()
            ->income()
            ->create(['amount_minor' => 5000, 'occurred_at' => $now]);
        EconomicTransactionParticipant::factory()->create([
            'transaction_id' => $sharedIncome->id,
            'household_member_id' => $this->user->householdMemberships()->first()->id,
            'split_type' => 'fixed',
            'amount_minor' => 500,
        ]);

        $response = $this->getJson('/api/v1/economy/me/totals')->assertOk();

        $response->assertJsonPath('data.own_total_minor', 3000);
        $response->assertJsonPath('data.shared_participation_minor', 1000);
        $response->assertJsonPath('data.personal_total_minor', 4000);
    }

    /**
     * Verify that unfiltered personal totals include undated expenses and exclude income.
     *
     * @return void This test method does not return a value.
     */
    public function test_personal_totals_include_undated_expenses_and_exclude_income(): void
    {
        EconomicTransaction::factory()->private()->createdBy($this->user)->create([
            'amount_minor' => 15000,
            'occurred_at' => '2026-09-03 12:00:00',
        ]);
        EconomicTransaction::factory()->private()->createdBy($this->user)->income()->create([
            'amount_minor' => 1000,
            'occurred_at' => null,
        ]);
        EconomicTransaction::factory()->forHousehold($this->household)->createdBy($this->user)->create([
            'amount_minor' => 2000,
            'occurred_at' => null,
        ]);

        $response = $this->getJson('/api/v1/economy/me/totals')->assertOk();

        $response->assertJsonPath('data.without_house_minor', 15000);
        $response->assertJsonPath('data.own_total_minor', 17000);
        $response->assertJsonPath('data.shared_participation_minor', 0);
        $response->assertJsonPath('data.personal_total_minor', 17000);
        $response->assertJsonPath('data.by_currency.EUR', 17000);
    }

    /**
     * Verify that a requested period restricts personal expenses to the selected month.
     *
     * @return void This test method does not return a value.
     */
    public function test_personal_totals_respect_an_explicit_period(): void
    {
        EconomicTransaction::factory()->private()->createdBy($this->user)->create([
            'amount_minor' => 1000,
            'occurred_at' => '2026-01-15 12:00:00',
        ]);
        EconomicTransaction::factory()->private()->createdBy($this->user)->create([
            'amount_minor' => 2000,
            'occurred_at' => '2026-02-15 12:00:00',
        ]);
        EconomicTransaction::factory()->private()->createdBy($this->user)->create([
            'amount_minor' => 3000,
            'occurred_at' => null,
        ]);

        $response = $this->getJson('/api/v1/economy/me/totals?period=2026-01')->assertOk();

        $response->assertJsonPath('data.period', '2026-01');
        $response->assertJsonPath('data.without_house_minor', 1000);
        $response->assertJsonPath('data.own_total_minor', 1000);
        $response->assertJsonPath('data.personal_total_minor', 1000);
        $response->assertJsonPath('data.by_currency.EUR', 1000);
    }

    /**
     * Verify that personal totals report different currencies without conversion.
     *
     * @return void This test method does not return a value.
     */
    public function test_personal_totals_group_by_currency_without_conversion(): void
    {
        EconomicTransaction::factory()->private()->createdBy($this->user)->create(['amount_minor' => 1000, 'currency' => 'EUR', 'occurred_at' => now()]);
        EconomicTransaction::factory()->private()->createdBy($this->user)->create(['amount_minor' => 500, 'currency' => 'USD', 'occurred_at' => now()]);
        $otherUser = User::factory()->create();
        HouseholdMember::factory()->create([
            'household_id' => $this->household->id,
            'user_id' => $otherUser->id,
        ]);
        $sharedTransaction = EconomicTransaction::factory()
            ->forHousehold($this->household)
            ->createdBy($otherUser)
            ->shared()
            ->create(['amount_minor' => 1000, 'currency' => 'USD', 'occurred_at' => now()]);
        EconomicTransactionParticipant::factory()->create([
            'transaction_id' => $sharedTransaction->id,
            'household_member_id' => $this->user->householdMemberships()->firstOrFail()->id,
            'split_type' => 'fixed',
            'amount_minor' => 200,
        ]);

        $response = $this->getJson('/api/v1/economy/me/totals')->assertOk();

        $this->assertSame(1000, $response->json('data.by_currency.EUR'));
        $this->assertSame(700, $response->json('data.by_currency.USD'));
    }

    /**
     * Verify that transaction creation requires an amount.
     *
     * @return void This test method does not return a value.
     */
    public function test_amount_is_required(): void
    {
        $this->postJson("/api/v1/households/{$this->household->id}/economy/transactions", [
            'type' => 'expense',
            'scope' => 'personal',
            'title' => 'Sin importe',
            'currency' => 'EUR',
        ])->assertJsonValidationErrors(['amount_minor']);
    }

    /**
     * Verify that transaction amounts must be positive.
     *
     * @return void This test method does not return a value.
     */
    public function test_amount_must_be_positive(): void
    {
        $this->postJson("/api/v1/households/{$this->household->id}/economy/transactions", [
            'type' => 'expense',
            'scope' => 'personal',
            'title' => 'Negativo',
            'amount_minor' => -5,
            'currency' => 'EUR',
        ])->assertJsonValidationErrors(['amount_minor']);
    }

    /**
     * Verify that transaction types are restricted to supported values.
     *
     * @return void This test method does not return a value.
     */
    public function test_type_must_be_valid(): void
    {
        $this->postJson("/api/v1/households/{$this->household->id}/economy/transactions", [
            'type' => 'weird',
            'scope' => 'personal',
            'title' => 'Inválido',
            'amount_minor' => 500,
            'currency' => 'EUR',
        ])->assertJsonValidationErrors(['type']);
    }

    /**
     * Verify that shared transactions require at least one participant.
     *
     * @return void This test method does not return a value.
     */
    public function test_participants_required_for_shared_scope(): void
    {
        $this->postJson("/api/v1/households/{$this->household->id}/economy/transactions", [
            'type' => 'expense',
            'scope' => 'shared',
            'title' => 'Compartida sin participantes',
            'amount_minor' => 500,
            'currency' => 'EUR',
        ])->assertJsonValidationErrors(['participants']);
    }

    /**
     * Verify that equal participant shares persist amounts matching the transaction total.
     *
     * @return void This test method does not return a value.
     */
    public function test_equal_participants_are_persisted_with_the_full_amount(): void
    {
        $other = User::factory()->create();
        $otherMember = HouseholdMember::factory()->create([
            'household_id' => $this->household->id,
            'user_id' => $other->id,
        ]);
        $ownMember = $this->user->householdMemberships()->firstOrFail();

        $response = $this->postJson("/api/v1/households/{$this->household->id}/economy/transactions", [
            'type' => 'expense',
            'scope' => 'shared',
            'title' => 'Compra compartida',
            'amount_minor' => 1001,
            'currency' => 'EUR',
            'participants' => [
                ['household_member_id' => $ownMember->id, 'split_type' => 'equal'],
                ['household_member_id' => $otherMember->id, 'split_type' => 'equal'],
            ],
        ])->assertCreated();

        $transactionId = $response->json('data.id');
        $this->assertSame(1001, (int) EconomicTransactionParticipant::query()
            ->where('transaction_id', $transactionId)
            ->sum('amount_minor'));
    }

    /**
     * Verify that changing a shared transaction total recalculates equal participant shares.
     *
     * @return void This test method does not return a value.
     */
    public function test_updating_shared_amount_recalculates_equal_participants(): void
    {
        $member = $this->user->householdMemberships()->firstOrFail();
        $transaction = EconomicTransaction::factory()->forHousehold($this->household)->createdBy($this->user)->shared()->create([
            'amount_minor' => 1000,
        ]);
        EconomicTransactionParticipant::factory()->create([
            'transaction_id' => $transaction->id,
            'household_member_id' => $member->id,
            'split_type' => 'equal',
            'amount_minor' => 1000,
        ]);

        $this->patchJson("/api/v1/households/{$this->household->id}/economy/transactions/{$transaction->id}", [
            'amount_minor' => 2000,
        ])->assertOk();

        $this->assertDatabaseHas('economic_transaction_participants', [
            'transaction_id' => $transaction->id,
            'amount_minor' => 2000,
        ]);
    }

    /**
     * Verify that household statistics exclude personal transactions owned by other members.
     *
     * @return void This test method does not return a value.
     */
    public function test_household_stats_do_not_expose_other_members_personal_transactions(): void
    {
        $otherUser = User::factory()->create();
        HouseholdMember::factory()->create([
            'household_id' => $this->household->id,
            'user_id' => $otherUser->id,
        ]);

        EconomicTransaction::factory()->forHousehold($this->household)->createdBy($this->user)->create([
            'amount_minor' => 1000,
        ]);
        EconomicTransaction::factory()->forHousehold($this->household)->createdBy($otherUser)->create([
            'amount_minor' => 9000,
        ]);
        EconomicTransaction::factory()->forHousehold($this->household)->createdBy($otherUser)->shared()->create([
            'amount_minor' => 2000,
        ]);

        $stats = app(GetEconomyStats::class)->execute($this->household, $this->user);

        $this->assertSame(3000, $stats['expenses_minor']);
    }

    /**
     * Verify that the personal index excludes household transactions the user can no longer access.
     *
     * @return void This test method does not return a value.
     */
    public function test_personal_index_excludes_transactions_from_households_the_user_does_not_belong_to(): void
    {
        $formerHousehold = Household::factory()->create();
        $inaccessibleTransaction = EconomicTransaction::factory()
            ->forHousehold($formerHousehold)
            ->createdBy($this->user)
            ->create();

        $response = $this->getJson('/api/v1/economy/me/transactions')->assertOk();

        $this->assertNotContains($inaccessibleTransaction->id, collect($response->json('data'))->pluck('id'));
        $this->getJson('/api/v1/economy/me/totals')
            ->assertOk()
            ->assertJsonPath('data.own_total_minor', 0);
    }
}
