<?php

namespace Tests\Feature\Economy;

use App\Actions\Economy\GetPersonalTotalsStats;
use App\Enums\EconomicImportStatus;
use App\Enums\HouseholdRole;
use App\Http\Requests\StoreEconomicTransactionRequest;
use App\Models\Contact;
use App\Models\EconomicDocument;
use App\Models\EconomicImport;
use App\Models\EconomicTransaction;
use App\Models\EconomicTransactionParticipant;
use App\Models\Household;
use App\Models\HouseholdMember;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Verifies that shared splits accept external participants next to household members.
 */
final class ExternalParticipantTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Household $household;

    private HouseholdMember $ownerMembership;

    /**
     * Prepare an authenticated household administrator with one membership.
     *
     * @return void This setup method does not return a value.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        $this->user = User::factory()->create();
        $this->user->assignRole('user');

        $this->household = Household::factory()->create();
        $this->ownerMembership = HouseholdMember::factory()->create([
            'household_id' => $this->household->id,
            'user_id' => $this->user->id,
            'role' => HouseholdRole::Admin,
        ]);

        Sanctum::actingAs($this->user);
    }

    public function test_equal_split_rules_allow_the_empty_amounts_the_form_sends(): void
    {
        // The web form always sends `amount` and `percentage` keys, empty for an equal split.
        // Without `nullable` they are present-but-empty, so `numeric` rejects every shared
        // transaction created from the browser. The API path hides this because it normalises
        // empty strings to null first, so this asserts the rule shape directly.
        $rules = (new StoreEconomicTransactionRequest)->rules();

        $this->assertContains('nullable', $rules['participants.*.amount']);
        $this->assertContains('nullable', $rules['participants.*.percentage']);
    }

    /**
     * Build a shared transaction payload for the household.
     *
     * @param  array<int, array<string, mixed>>  $participants  The participant rows to submit.
     * @param  array<string, mixed>  $extra  Additional payload fields merged into the defaults.
     * @return array<string, mixed> The request payload.
     */
    private function sharedPayload(array $participants, array $extra = []): array
    {
        return array_merge([
            'type' => 'expense',
            'scope' => 'shared',
            'title' => 'Shared dinner',
            'amount_minor' => 10000,
            'currency' => 'EUR',
            'participants' => $participants,
        ], $extra);
    }

    /**
     * Build the household shared transaction endpoint used by the API.
     *
     * @return string The transaction collection URL.
     */
    private function storeUrl(): string
    {
        return "/api/v1/households/{$this->household->id}/economy/transactions";
    }

    /**
     * Add a second user to the household and return the membership.
     *
     * @return HouseholdMember The partner membership.
     */
    private function addPartnerMembership(): HouseholdMember
    {
        return HouseholdMember::factory()->create([
            'household_id' => $this->household->id,
            'user_id' => User::factory()->create()->id,
            'role' => HouseholdRole::Member,
        ]);
    }

    /**
     * Find the anonymous portion of a persisted transaction.
     *
     * @param  EconomicTransaction  $transaction  The transaction to inspect.
     * @return EconomicTransactionParticipant The anonymous participant row.
     */
    private function anonymousParticipant(EconomicTransaction $transaction): EconomicTransactionParticipant
    {
        return $transaction->participants()
            ->whereNull('household_member_id')
            ->whereNull('display_name')
            ->firstOrFail();
    }

    /**
     * Verify that a named external participant is persisted and shares the total.
     *
     * @return void This test method does not return a value.
     */
    public function test_shared_transaction_persists_a_named_external_participant(): void
    {
        $response = $this->postJson($this->storeUrl(), $this->sharedPayload([
            ['household_member_id' => $this->ownerMembership->id, 'split_type' => 'equal'],
            ['display_name' => 'Juan', 'split_type' => 'equal'],
        ]));

        $response->assertCreated();

        $transaction = EconomicTransaction::query()->latest('created_at')->firstOrFail();
        $external = $transaction->participants()->where('display_name', 'Juan')->firstOrFail();

        $this->assertNull($external->household_member_id);
        $this->assertNull($external->contact_id);
        $this->assertSame(1, $external->share_count);
        $this->assertSame(5000, $external->amount_minor);
        $this->assertSame(
            $this->ownerMembership->id,
            $transaction->participants()->whereNotNull('household_member_id')->value('household_member_id'),
        );
        $this->assertSame(10000, (int) $transaction->participants()->sum('amount_minor'));
    }

    /**
     * Verify that one anonymous portion is persisted and counted as several heads.
     *
     * @return void This test method does not return a value.
     */
    public function test_shared_transaction_persists_one_anonymous_portion_with_share_count(): void
    {
        $response = $this->postJson($this->storeUrl(), $this->sharedPayload([
            ['household_member_id' => $this->ownerMembership->id, 'split_type' => 'equal'],
            ['share_count' => 3, 'split_type' => 'equal'],
        ]));

        $response->assertCreated();

        $transaction = EconomicTransaction::query()->latest('created_at')->firstOrFail();
        $anonymous = $this->anonymousParticipant($transaction);

        $this->assertSame(3, $anonymous->share_count);
        $this->assertNull($anonymous->contact_id);
        $this->assertSame(7500, $anonymous->amount_minor);
        $this->assertSame(2500, (int) $transaction->participants()
            ->whereNotNull('household_member_id')
            ->sum('amount_minor'));
        $this->assertSame(10000, (int) $transaction->participants()->sum('amount_minor'));
    }

    /**
     * Verify that a member-only split keeps its previous behaviour.
     *
     * @return void This test method does not return a value.
     */
    public function test_member_only_split_keeps_working(): void
    {
        $partner = $this->addPartnerMembership();

        $response = $this->postJson($this->storeUrl(), $this->sharedPayload([
            ['household_member_id' => $this->ownerMembership->id, 'split_type' => 'equal'],
            ['household_member_id' => $partner->id, 'split_type' => 'equal'],
        ]));

        $response->assertCreated();

        $transaction = EconomicTransaction::query()->latest('created_at')->firstOrFail();

        $this->assertSame(2, $transaction->participants()->count());
        $this->assertSame(0, $transaction->participants()->whereNull('household_member_id')->count());
        $this->assertSame(10000, (int) $transaction->participants()->sum('amount_minor'));
        $this->assertSame(
            [$this->ownerMembership->id, $partner->id],
            $transaction->participants()->orderBy('amount_minor')->pluck('household_member_id')->all(),
        );
        $this->assertSame([5000, 5000], $transaction->participants()
            ->orderBy('amount_minor')
            ->pluck('amount_minor')
            ->all());
    }

    /**
     * Verify that an external participant cannot reference a contact of another user.
     *
     * @return void This test method does not return a value.
     */
    public function test_external_participant_with_foreign_contact_is_rejected(): void
    {
        $contact = Contact::create([
            'user_id' => User::factory()->create()->id,
            'type' => 'person',
            'display_name' => 'Ana',
        ]);

        $response = $this->postJson($this->storeUrl(), $this->sharedPayload([
            ['household_member_id' => $this->ownerMembership->id, 'split_type' => 'equal'],
            ['display_name' => 'Ana', 'contact_id' => $contact->id, 'split_type' => 'equal'],
        ]));

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['participants.1.contact_id']);

        $this->assertDatabaseCount('economic_transactions', 0);
    }

    /**
     * Verify that a shared split accepts at most one anonymous portion.
     *
     * @return void This test method does not return a value.
     */
    public function test_two_anonymous_portions_are_rejected(): void
    {
        $response = $this->postJson($this->storeUrl(), $this->sharedPayload([
            ['household_member_id' => $this->ownerMembership->id, 'split_type' => 'equal'],
            ['split_type' => 'equal'],
            ['share_count' => 2, 'split_type' => 'equal'],
        ]));

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['participants'])
            ->assertJsonPath(
                'errors.participants.0',
                __('app.validation.participants_single_anonymous'),
            );

        $this->assertDatabaseCount('economic_transactions', 0);
    }

    /**
     * Verify that updating only the amount keeps the external participant of the split.
     *
     * @return void This test method does not return a value.
     */
    public function test_updating_the_amount_keeps_the_external_participant(): void
    {
        $this->postJson($this->storeUrl(), $this->sharedPayload([
            ['household_member_id' => $this->ownerMembership->id, 'split_type' => 'equal'],
            ['display_name' => 'Juan', 'split_type' => 'equal'],
        ]))->assertCreated();

        $transaction = EconomicTransaction::query()->latest('created_at')->firstOrFail();

        $this->putJson("{$this->storeUrl()}/{$transaction->id}", [
            'amount_minor' => 12000,
        ])->assertOk();

        $external = $transaction->refresh()->participants()->where('display_name', 'Juan')->firstOrFail();

        $this->assertNull($external->household_member_id);
        $this->assertSame(1, $external->share_count);
        $this->assertSame(2, $transaction->participants()->count());
        $this->assertSame(12000, (int) $transaction->participants()->sum('amount_minor'));
    }

    /**
     * Verify that every participant flavour is serialized with its identity fields.
     *
     * @return void This test method does not return a value.
     */
    public function test_shared_transaction_serializes_member_external_and_anonymous_participants(): void
    {
        $response = $this->postJson($this->storeUrl(), $this->sharedPayload([
            ['household_member_id' => $this->ownerMembership->id, 'split_type' => 'equal'],
            ['display_name' => 'Juan', 'split_type' => 'equal'],
            ['share_count' => 2, 'split_type' => 'equal'],
        ], ['amount_minor' => 12000]));

        $response->assertCreated();

        $participants = collect($this->getJson(
            '/api/v1/households/'.$this->household->id.'/economy/transactions/'.$response->json('data.id'),
        )->assertOk()->json('data.participants'));

        $this->assertCount(3, $participants);

        $expectedKeys = [
            'id', 'split_type', 'amount', 'percentage', 'amount_minor',
            'display_name', 'contact_id', 'share_count', 'household_member',
        ];

        $memberRow = $participants->firstWhere('household_member.id', $this->ownerMembership->id);

        $this->assertNotNull($memberRow);
        $this->assertSame($expectedKeys, array_keys($memberRow));
        $this->assertNull($memberRow['display_name']);
        $this->assertNull($memberRow['contact_id']);
        $this->assertSame(1, $memberRow['share_count']);
        $this->assertSame(['id', 'user', 'contact'], array_keys($memberRow['household_member']));

        $externalRow = $participants->firstWhere('display_name', 'Juan');

        $this->assertNotNull($externalRow);
        $this->assertSame($expectedKeys, array_keys($externalRow));
        $this->assertNull($externalRow['household_member']);
        $this->assertNull($externalRow['contact_id']);
        $this->assertSame(1, $externalRow['share_count']);
        $this->assertSame(3000, $externalRow['amount_minor']);

        $anonymousRow = $participants->first(fn (array $row): bool => $row['display_name'] === null
            && $row['household_member'] === null);

        $this->assertNotNull($anonymousRow);
        $this->assertSame($expectedKeys, array_keys($anonymousRow));
        $this->assertNull($anonymousRow['contact_id']);
        $this->assertSame(2, $anonymousRow['share_count']);
        $this->assertSame(6000, $anonymousRow['amount_minor']);
    }

    /**
     * Verify that a generated occurrence copies the external participant of the series.
     *
     * @return void This test method does not return a value.
     */
    public function test_recurring_shared_transaction_copies_the_external_participant(): void
    {
        $response = $this->postJson($this->storeUrl(), $this->sharedPayload([
            ['household_member_id' => $this->ownerMembership->id, 'split_type' => 'equal'],
            ['display_name' => 'Juan', 'share_count' => 1, 'split_type' => 'equal'],
        ], [
            'title' => 'Shared rent',
            'amount_minor' => 90000,
            'occurred_at' => '2026-09-10',
            'recurrence_frequency' => 'monthly',
            'recurrence_interval' => 1,
        ]));

        $response->assertCreated();

        $source = EconomicTransaction::query()->latest('created_at')->firstOrFail();

        $this->assertSame(0, Artisan::call('economy:generate-recurring-transactions', [
            '--date' => '2026-10-10',
        ]));

        $occurrence = $source->generatedOccurrences()->firstOrFail();
        $copiedExternal = $occurrence->participants()->where('display_name', 'Juan')->firstOrFail();

        $this->assertNull($copiedExternal->household_member_id);
        $this->assertSame(1, $copiedExternal->share_count);
        $this->assertSame(45000, $copiedExternal->amount_minor);
        $this->assertSame(2, $occurrence->participants()->count());
        $this->assertSame(90000, (int) $occurrence->participants()->sum('amount_minor'));
    }

    /**
     * Verify that external portions of someone else's shared transaction never inflate the
     * personal totals of a household member.
     *
     * @return void This test method does not return a value.
     */
    public function test_external_participants_do_not_inflate_personal_totals(): void
    {
        $partner = User::factory()->create();
        $partner->assignRole('user');
        $partnerMembership = HouseholdMember::factory()->create([
            'household_id' => $this->household->id,
            'user_id' => $partner->id,
            'role' => HouseholdRole::Member,
        ]);

        Sanctum::actingAs($partner);

        $this->postJson($this->storeUrl(), $this->sharedPayload([
            ['household_member_id' => $this->ownerMembership->id, 'split_type' => 'equal'],
            ['household_member_id' => $partnerMembership->id, 'split_type' => 'equal'],
            ['share_count' => 2, 'split_type' => 'equal'],
        ]))->assertCreated();

        $transaction = EconomicTransaction::query()->latest('created_at')->firstOrFail();
        $anonymous = $this->anonymousParticipant($transaction);

        $this->assertSame(5000, $anonymous->amount_minor);
        $this->assertSame(2500, (int) $transaction->participants()
            ->where('household_member_id', $this->ownerMembership->id)
            ->value('amount_minor'));

        $totals = (new GetPersonalTotalsStats)->execute($this->user);

        $this->assertSame(0, $totals['own_total_minor']);
        $this->assertSame(2500, $totals['shared_participation_minor']);
        $this->assertSame(2500, $totals['personal_total_minor']);
    }

    /**
     * Verify that confirming an economic import carries named and anonymous externals into the
     * created transaction.
     *
     * @return void This test method does not return a value.
     */
    public function test_import_confirmation_persists_external_participants(): void
    {
        $document = EconomicDocument::factory()
            ->forHousehold($this->household)
            ->uploadedBy($this->user)
            ->create();
        $import = EconomicImport::factory()
            ->forHousehold($this->household)
            ->createdBy($this->user)
            ->extracted([
                'title' => 'Shared dinner',
                'amount' => '100.00',
                'currency' => 'EUR',
            ])
            ->create(['document_id' => $document->id]);

        $response = $this->actingAs($this->user)->post(
            route('households.economy.imports.confirm', [$this->household, $import]),
            [
                'scope' => 'shared',
                'participants' => [
                    ['household_member_id' => $this->ownerMembership->id, 'split_type' => 'equal'],
                    ['display_name' => 'Juan', 'split_type' => 'equal'],
                    ['share_count' => 2, 'split_type' => 'equal'],
                ],
            ],
        );

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();

        $transaction = EconomicTransaction::query()->latest('created_at')->firstOrFail();
        $external = $transaction->participants()->where('display_name', 'Juan')->firstOrFail();
        $anonymous = $this->anonymousParticipant($transaction);

        $this->assertSame(3, $transaction->participants()->count());
        $this->assertNull($external->household_member_id);
        $this->assertNull($external->contact_id);
        $this->assertSame(1, $external->share_count);
        $this->assertSame(2500, $external->amount_minor);
        $this->assertSame(2, $anonymous->share_count);
        $this->assertSame(5000, $anonymous->amount_minor);
        $this->assertSame(10000, (int) $transaction->participants()->sum('amount_minor'));
        $this->assertSame(EconomicImportStatus::Confirmed, $import->fresh()->status);
    }
}
