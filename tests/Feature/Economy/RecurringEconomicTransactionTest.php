<?php

namespace Tests\Feature\Economy;

use App\Enums\HouseholdRole;
use App\Enums\RecurrenceFrequency;
use App\Models\EconomicTransaction;
use App\Models\Household;
use App\Models\HouseholdMember;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RecurringEconomicTransactionTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Verify that a user can create a monthly recurring private transaction.
     *
     * @return void This test method does not return a value.
     */
    public function test_user_can_create_monthly_private_recurring_transaction(): void
    {
        $user = $this->authenticateUser();

        $response = $this->postJson('/api/v1/economy/me/transactions', [
            'type' => 'expense',
            'scope' => 'personal',
            'title' => 'Mortgage',
            'amount_minor' => 85000,
            'currency' => 'EUR',
            'occurred_at' => '2026-09-04',
            'recurrence_frequency' => 'monthly',
            'recurrence_interval' => 1,
            'recurrence_ends_at' => '2027-09-04',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.recurrence_frequency', 'monthly')
            ->assertJsonPath('data.recurrence_interval', 1)
            ->assertJsonPath('data.recurrence_ends_at', '2027-09-04');
        $this->assertDatabaseHas('economic_transactions', [
            'id' => $response->json('data.id'),
            'created_by' => $user->id,
            'household_id' => null,
            'recurrence_frequency' => 'monthly',
            'recurrence_interval' => 1,
            'recurrence_next_at' => '2026-10-04 00:00:00',
        ]);
    }

    /**
     * Verify that a weekly recurrence accepts several weekdays and schedules the nearest one.
     *
     * @return void This test method does not return a value.
     */
    public function test_user_can_create_weekly_recurrence_for_multiple_weekdays(): void
    {
        $this->authenticateUser();

        $response = $this->postJson('/api/v1/economy/me/transactions', [
            'type' => 'expense',
            'scope' => 'personal',
            'title' => 'Training',
            'amount_minor' => 2500,
            'currency' => 'EUR',
            'occurred_at' => '2026-09-07',
            'recurrence_frequency' => 'weekly',
            'recurrence_interval' => 1,
            'recurrence_weekdays' => [1, 4],
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.recurrence_frequency', 'weekly')
            ->assertJsonPath('data.recurrence_weekdays', [1, 4]);
        $this->assertDatabaseHas('economic_transactions', [
            'id' => $response->json('data.id'),
            'recurrence_next_at' => '2026-09-10 00:00:00',
        ]);
    }

    /**
     * Verify that at least one weekday is required when weekly recurrence is selected.
     *
     * @return void This test method does not return a value.
     */
    public function test_weekly_recurrence_requires_at_least_one_weekday(): void
    {
        $this->authenticateUser();

        $response = $this->postJson('/api/v1/economy/me/transactions', [
            'type' => 'expense',
            'scope' => 'personal',
            'title' => 'Training',
            'amount' => 25,
            'currency' => 'EUR',
            'occurred_at' => '2026-09-07',
            'recurrence_frequency' => 'weekly',
            'recurrence_interval' => 1,
            'recurrence_weekdays' => [],
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['recurrence_weekdays'])
            ->assertJsonPath(
                'errors.recurrence_weekdays.0',
                __('app.validation.recurrence_weekdays_required'),
            );
        $this->assertDatabaseCount('economic_transactions', 0);
    }

    /**
     * Verify that a recurring transaction requires an occurrence date.
     *
     * @return void This test method does not return a value.
     */
    public function test_recurring_transaction_requires_an_occurrence_date(): void
    {
        $this->authenticateUser();

        $response = $this->postJson('/api/v1/economy/me/transactions', [
            'type' => 'expense',
            'scope' => 'personal',
            'title' => 'Subscription',
            'amount' => 15,
            'currency' => 'EUR',
            'recurrence_frequency' => 'monthly',
            'recurrence_interval' => 1,
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['occurred_at'])
            ->assertJsonPath(
                'errors.occurred_at.0',
                __('app.validation.recurrence_requires_date'),
            );
        $this->assertDatabaseCount('economic_transactions', 0);
    }

    /**
     * Verify that a recurrence cannot end before its first occurrence.
     *
     * @return void This test method does not return a value.
     */
    public function test_recurrence_end_date_cannot_precede_occurrence_date(): void
    {
        $this->authenticateUser();

        $response = $this->postJson('/api/v1/economy/me/transactions', [
            'type' => 'expense',
            'scope' => 'personal',
            'title' => 'Subscription',
            'amount' => 15,
            'currency' => 'EUR',
            'occurred_at' => '2026-09-04',
            'recurrence_frequency' => 'monthly',
            'recurrence_interval' => 1,
            'recurrence_ends_at' => '2026-09-03',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['recurrence_ends_at']);
        $this->assertDatabaseCount('economic_transactions', 0);
    }

    /**
     * Verify that the generator command rejects an invalid date with a localized message.
     *
     * @return void This test method does not return a value.
     */
    public function test_command_rejects_an_invalid_generation_date(): void
    {
        $exitCode = Artisan::call('economy:generate-recurring-transactions', [
            '--date' => 'not-a-date',
        ]);
        $expectedMessage = __('console.economy.recurring.invalid_date');

        if (! is_string($expectedMessage) || $expectedMessage === '') {
            self::fail('The recurring transaction command message must be a non-empty translation.');
        }

        $this->assertSame(1, $exitCode);
        $this->assertStringEndsWith(
            $expectedMessage,
            trim(Artisan::output()),
        );
    }

    /**
     * Verify that generation catches up monthly occurrences, preserves the anchor day, and is idempotent.
     *
     * @return void This test method does not return a value.
     */
    public function test_command_generates_independent_shared_occurrences_once(): void
    {
        $owner = User::factory()->create();
        $participant = User::factory()->create();
        $household = Household::factory()->create();
        HouseholdMember::factory()->create([
            'household_id' => $household->id,
            'user_id' => $owner->id,
            'role' => HouseholdRole::Admin,
        ]);
        $participantMembership = HouseholdMember::factory()->create([
            'household_id' => $household->id,
            'user_id' => $participant->id,
        ]);
        $source = EconomicTransaction::factory()
            ->forHousehold($household)
            ->createdBy($owner)
            ->shared()
            ->create([
                'title' => 'Household insurance',
                'amount_minor' => 12000,
                'occurred_at' => '2026-08-31 09:30:00',
                'recurrence_frequency' => RecurrenceFrequency::Monthly,
                'recurrence_interval' => 1,
                'recurrence_next_at' => '2026-09-30 09:30:00',
                'recurrence_ends_at' => '2026-10-31',
            ]);
        $source->items()->create([
            'name' => 'Insurance premium',
            'quantity' => 1,
            'unit_amount_minor' => 12000,
            'subtotal_minor' => 12000,
            'tax_amount_minor' => 0,
            'total_minor' => 12000,
            'position' => 1,
        ]);
        $source->taxes()->create([
            'name' => 'Tax',
            'rate' => 0,
            'taxable_base_minor' => 12000,
            'tax_amount_minor' => 0,
        ]);
        $source->participants()->create([
            'household_member_id' => $participantMembership->id,
            'split_type' => 'fixed',
            'amount_minor' => 4000,
            'percentage' => null,
        ]);

        $this->assertSame(0, Artisan::call('economy:generate-recurring-transactions', [
            '--date' => '2026-10-31',
        ]));

        $occurrences = $source->generatedOccurrences()->orderBy('occurred_at')->get();
        $this->assertCount(2, $occurrences);
        $this->assertSame(
            ['2026-09-30', '2026-10-31'],
            $occurrences->map(fn (EconomicTransaction $transaction): ?string => $transaction->occurred_at?->toDateString())->all(),
        );
        $lastOccurrence = $source->generatedOccurrences()->latest('occurred_at')->firstOrFail();
        $this->assertSame(1, $lastOccurrence->items()->count());
        $this->assertSame(1, $lastOccurrence->taxes()->count());
        $this->assertSame(1, $lastOccurrence->participants()->count());
        $this->assertNull($source->refresh()->recurrence_next_at);

        $this->assertSame(0, Artisan::call('economy:generate-recurring-transactions', [
            '--date' => '2026-10-31',
        ]));
        $this->assertSame(2, $source->generatedOccurrences()->count());
    }

    /**
     * Verify that selected weekdays are generated only during each active interval week.
     *
     * @return void This test method does not return a value.
     */
    public function test_command_generates_selected_weekdays_with_a_two_week_interval(): void
    {
        $user = User::factory()->create();
        $source = EconomicTransaction::factory()->private()->createdBy($user)->create([
            'occurred_at' => '2026-09-07 08:15:00',
            'recurrence_frequency' => RecurrenceFrequency::Weekly,
            'recurrence_interval' => 2,
            'recurrence_weekdays' => [1, 4],
            'recurrence_next_at' => '2026-09-10 08:15:00',
        ]);

        $this->assertSame(0, Artisan::call('economy:generate-recurring-transactions', [
            '--date' => '2026-09-24',
        ]));

        $this->assertSame(
            ['2026-09-10', '2026-09-21', '2026-09-24'],
            $source->generatedOccurrences()
                ->orderBy('occurred_at')
                ->get()
                ->map(fn (EconomicTransaction $transaction): ?string => $transaction->occurred_at?->toDateString())
                ->all(),
        );
        $this->assertSame(
            '2026-10-05 08:15:00',
            $source->refresh()->recurrence_next_at?->format('Y-m-d H:i:s'),
        );
    }

    /**
     * Verify that editing a generated occurrence cannot create a nested recurrence series.
     *
     * @return void This test method does not return a value.
     */
    public function test_generated_occurrence_cannot_start_another_recurrence(): void
    {
        $user = $this->authenticateUser();
        $source = EconomicTransaction::factory()->private()->createdBy($user)->create([
            'occurred_at' => '2026-09-04',
            'recurrence_frequency' => RecurrenceFrequency::Monthly,
            'recurrence_interval' => 1,
            'recurrence_next_at' => '2026-10-04',
        ]);
        $occurrence = EconomicTransaction::factory()->private()->createdBy($user)->create([
            'occurred_at' => '2026-10-04',
            'recurrence_parent_id' => $source->id,
        ]);

        $response = $this->patchJson("/api/v1/economy/me/transactions/{$occurrence->id}", [
            'recurrence_frequency' => 'weekly',
            'recurrence_interval' => 1,
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['recurrence_frequency'])
            ->assertJsonPath(
                'errors.recurrence_frequency.0',
                __('app.validation.generated_occurrence_cannot_recur'),
            );
        $this->assertNull($occurrence->refresh()->recurrence_frequency);
    }

    /**
     * Verify that updating a recurrence source schedules future occurrences after existing history.
     *
     * @return void This test method does not return a value.
     */
    public function test_user_can_update_a_recurrence_source_schedule(): void
    {
        $user = $this->authenticateUser();
        $source = EconomicTransaction::factory()->private()->createdBy($user)->create([
            'occurred_at' => '2026-09-04',
            'recurrence_frequency' => RecurrenceFrequency::Monthly,
            'recurrence_interval' => 1,
            'recurrence_next_at' => '2026-10-04',
        ]);
        EconomicTransaction::factory()->private()->createdBy($user)->create([
            'occurred_at' => '2026-10-04',
            'recurrence_parent_id' => $source->id,
        ]);

        $response = $this->patchJson("/api/v1/economy/me/transactions/{$source->id}", [
            'recurrence_frequency' => 'weekly',
            'recurrence_interval' => 2,
            'recurrence_weekdays' => [1, 4],
            'recurrence_ends_at' => '2026-12-31',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.recurrence_frequency', 'weekly')
            ->assertJsonPath('data.recurrence_interval', 2)
            ->assertJsonPath('data.recurrence_weekdays', [1, 4])
            ->assertJsonPath('data.recurrence_ends_at', '2026-12-31');
        $this->assertDatabaseHas('economic_transactions', [
            'id' => $source->id,
            'recurrence_frequency' => 'weekly',
            'recurrence_interval' => 2,
            'recurrence_next_at' => '2026-10-12 00:00:00',
        ]);
    }

    /**
     * Verify that a user can stop future generation from a recurrence source.
     *
     * @return void This test method does not return a value.
     */
    public function test_user_can_disable_a_recurrence_source(): void
    {
        $user = $this->authenticateUser();
        $source = EconomicTransaction::factory()->private()->createdBy($user)->create([
            'occurred_at' => '2026-09-04',
            'recurrence_frequency' => RecurrenceFrequency::Monthly,
            'recurrence_interval' => 1,
            'recurrence_weekdays' => [1, 4],
            'recurrence_next_at' => '2026-10-04',
        ]);

        $response = $this->patchJson("/api/v1/economy/me/transactions/{$source->id}", [
            'recurrence_frequency' => null,
            'recurrence_interval' => 1,
            'recurrence_ends_at' => null,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.recurrence_frequency', null)
            ->assertJsonPath('data.recurrence_next_at', null);
        $source->refresh();
        $this->assertNull($source->recurrence_frequency);
        $this->assertNull($source->recurrence_interval);
        $this->assertNull($source->recurrence_weekdays);
        $this->assertNull($source->recurrence_ends_at);
        $this->assertNull($source->recurrence_next_at);
    }

    /**
     * Verify that deleting a recurrence source stops the series but preserves generated history.
     *
     * @return void This test method does not return a value.
     */
    public function test_deleting_recurrence_source_preserves_generated_occurrences(): void
    {
        $user = $this->authenticateUser();
        $source = EconomicTransaction::factory()->private()->createdBy($user)->create([
            'occurred_at' => '2026-09-04',
            'recurrence_frequency' => RecurrenceFrequency::Monthly,
            'recurrence_interval' => 1,
            'recurrence_next_at' => '2026-10-04',
        ]);
        $occurrence = EconomicTransaction::factory()->private()->createdBy($user)->create([
            'occurred_at' => '2026-10-04',
            'recurrence_parent_id' => $source->id,
        ]);

        $this->deleteJson("/api/v1/economy/me/transactions/{$source->id}")
            ->assertNoContent();

        $this->assertModelMissing($source);
        $this->assertModelExists($occurrence);
        $this->assertNull($occurrence->refresh()->recurrence_parent_id);
    }

    /**
     * Create and authenticate a user with economy API permissions.
     *
     * @return User The authenticated user configured for an economy API request.
     */
    private function authenticateUser(): User
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $user = User::factory()->create();
        $user->assignRole('user');
        Sanctum::actingAs($user);

        return $user;
    }
}
