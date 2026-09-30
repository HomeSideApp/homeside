<?php

namespace Tests\Feature\Economy;

use App\Enums\EconomicImportStatus;
use App\Enums\HouseholdRole;
use App\Models\EconomicDocument;
use App\Models\EconomicImport;
use App\Models\EconomicTransaction;
use App\Models\Household;
use App\Models\HouseholdMember;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Verifies that direct UUID access across household and private route families
 * is denied without exposing economic resource data.
 */
class EconomicIdorTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private User $attacker;

    private Household $householdA;

    private Household $householdB;

    /**
     * Prepare resource owners, attackers, and isolated households for each security test.
     *
     * @return void This setup method does not return a value.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        $this->owner = User::factory()->create();
        $this->owner->assignRole('user');
        $this->attacker = User::factory()->create();
        $this->attacker->assignRole('user');

        $this->householdA = Household::factory()->create();
        $this->householdB = Household::factory()->create();

        HouseholdMember::factory()->create([
            'household_id' => $this->householdA->id,
            'user_id' => $this->owner->id,
            'role' => HouseholdRole::Admin,
        ]);
        HouseholdMember::factory()->create([
            'household_id' => $this->householdB->id,
            'user_id' => $this->attacker->id,
            'role' => HouseholdRole::Admin,
        ]);
    }

    /**
     * Verify that a user cannot read a transaction from an unrelated household by UUID.
     *
     * @return void This test method does not return a value.
     */
    public function test_user_b_cannot_read_transaction_of_household_a_by_uuid(): void
    {
        Sanctum::actingAs($this->attacker);
        $transaction = EconomicTransaction::factory()->forHousehold($this->householdA)->createdBy($this->owner)->shared()->create();

        $this->getJson("/api/v1/households/{$this->householdB->id}/economy/transactions/{$transaction->id}")
            ->assertForbidden();
    }

    /**
     * Verify that a user cannot update a transaction from an unrelated household by UUID.
     *
     * @return void This test method does not return a value.
     */
    public function test_user_b_cannot_update_transaction_of_household_a_by_uuid(): void
    {
        Sanctum::actingAs($this->attacker);
        $transaction = EconomicTransaction::factory()->forHousehold($this->householdA)->createdBy($this->owner)->shared()->create();

        $this->patchJson("/api/v1/households/{$this->householdB->id}/economy/transactions/{$transaction->id}", [
            'title' => 'hackeado',
        ])->assertForbidden();

        $this->assertDatabaseHas('economic_transactions', [
            'id' => $transaction->id,
            'title' => $transaction->title,
        ]);
    }

    /**
     * Verify that a user cannot delete a transaction from an unrelated household by UUID.
     *
     * @return void This test method does not return a value.
     */
    public function test_user_b_cannot_delete_transaction_of_household_a_by_uuid(): void
    {
        Sanctum::actingAs($this->attacker);
        $transaction = EconomicTransaction::factory()->forHousehold($this->householdA)->createdBy($this->owner)->shared()->create();

        $this->deleteJson("/api/v1/households/{$this->householdB->id}/economy/transactions/{$transaction->id}")
            ->assertForbidden();

        $this->assertDatabaseHas('economic_transactions', ['id' => $transaction->id]);
    }

    /**
     * Verify that household membership does not expose another member's personal transaction.
     *
     * @return void This test method does not return a value.
     */
    public function test_user_b_cannot_read_personal_scope_transaction_of_user_a2_in_shared_household(): void
    {
        // The attacker joins household A as an additional member.
        if (! HouseholdMember::where('household_id', $this->householdA->id)->where('user_id', $this->attacker->id)->exists()) {
            HouseholdMember::factory()->create([
                'household_id' => $this->householdA->id,
                'user_id' => $this->attacker->id,
            ]);
        }

        Sanctum::actingAs($this->attacker);
        $personal = EconomicTransaction::factory()->forHousehold($this->householdA)->createdBy($this->owner)->create();

        $this->getJson("/api/v1/households/{$this->householdA->id}/economy/transactions/{$personal->id}")
            ->assertForbidden();
    }

    /**
     * Verify that a user cannot read another user's private transaction through private routes.
     *
     * @return void This test method does not return a value.
     */
    public function test_cannot_read_private_transaction_of_another_user_via_me_route(): void
    {
        Sanctum::actingAs($this->attacker);
        $private = EconomicTransaction::factory()->private()->createdBy($this->owner)->create();

        $this->getJson("/api/v1/economy/me/transactions/{$private->id}")
            ->assertForbidden();
    }

    /**
     * Verify that a user cannot read another user's private import.
     *
     * @return void This test method does not return a value.
     */
    public function test_cannot_read_private_import_of_another_user(): void
    {
        Sanctum::actingAs($this->attacker);
        $import = EconomicImport::factory()->private()->createdBy($this->owner)->create();

        $this->getJson("/api/v1/economy/me/imports/{$import->id}")
            ->assertForbidden();
    }

    /**
     * Verify that a user cannot confirm another user's private import.
     *
     * @return void This test method does not return a value.
     */
    public function test_cannot_confirm_private_import_of_another_user(): void
    {
        Sanctum::actingAs($this->attacker);
        $import = EconomicImport::factory()->private()->createdBy($this->owner)
            ->extracted(['title' => 'X', 'amount' => '10.00', 'currency' => 'EUR'])
            ->create();

        // A forbidden response must not disclose the import state or payload.
        $this->withHeader('Idempotency-Key', 'foreign-private-import')
            ->postJson("/api/v1/economy/me/imports/{$import->id}/confirm", [])
            ->assertForbidden();

        $this->assertDatabaseHas('economic_imports', [
            'id' => $import->id,
            'status' => 'ready_for_review',
        ]);
    }

    /**
     * Verify that imports from another household cannot be retried or discarded.
     *
     * @return void This test method does not return a value.
     */
    public function test_cannot_retry_or_discard_import_of_another_household(): void
    {
        Sanctum::actingAs($this->attacker);
        $import = EconomicImport::factory()->forHousehold($this->householdA)->createdBy($this->owner)
            ->status(EconomicImportStatus::Failed)->create();

        $this->withHeader('Idempotency-Key', 'foreign-household-retry')
            ->postJson("/api/v1/households/{$this->householdB->id}/economy/imports/{$import->id}/retry")
            ->assertForbidden();
    }

    /**
     * Verify that private imports cannot reference another user's document.
     *
     * @return void This test method does not return a value.
     */
    public function test_cannot_create_import_with_document_of_another_scope_or_owner(): void
    {
        Sanctum::actingAs($this->attacker);
        $privateDoc = EconomicDocument::factory()->private()->uploadedBy($this->owner)->create();

        // The action scopes private document lookup to its uploader.
        $this->postJson('/api/v1/economy/me/imports', [
            'document_id' => $privateDoc->id,
        ])->assertNotFound();
    }

    /**
     * Verify that transactions cannot attach a document from another household.
     *
     * @return void This test method does not return a value.
     */
    public function test_cannot_attach_source_document_from_another_household(): void
    {
        Sanctum::actingAs($this->attacker);
        $foreignDoc = EconomicDocument::factory()->forHousehold($this->householdA)->uploadedBy($this->owner)->create();

        $this->postJson("/api/v1/households/{$this->householdB->id}/economy/transactions", [
            'type' => 'expense',
            'scope' => 'personal',
            'title' => 'Con doc ajeno',
            'amount_minor' => 500,
            'currency' => 'EUR',
            'source_document_id' => $foreignDoc->id,
        ])->assertJsonValidationErrors(['source_document_id']);
    }

    /**
     * Verify that a user cannot download another user's private document.
     *
     * @return void This test method does not return a value.
     */
    public function test_cannot_download_document_file_of_another_users_private_upload(): void
    {
        Sanctum::actingAs($this->attacker);
        Storage::fake('local');
        $doc = EconomicDocument::factory()->private()->uploadedBy($this->owner)->create([
            'path' => 'economy/documents/private/test.jpg',
        ]);
        Storage::disk('local')->put($doc->path, 'fake-content');

        $this->getJson("/api/v1/economy/me/documents/{$doc->id}")
            ->assertForbidden();
    }

    /**
     * Verify that transaction participants cannot belong to another household.
     *
     * @return void This test method does not return a value.
     */
    public function test_cannot_add_participant_who_is_member_of_another_household(): void
    {
        Sanctum::actingAs($this->owner);
        // The attacker already owns the household B membership created during setup.
        $foreignMember = $this->attacker->householdMemberships()->where('household_id', $this->householdB->id)->firstOrFail();

        $this->postJson("/api/v1/households/{$this->householdA->id}/economy/transactions", [
            'type' => 'expense',
            'scope' => 'shared',
            'title' => 'Reparto cruzado',
            'amount_minor' => 1000,
            'currency' => 'EUR',
            'participants' => [
                ['household_member_id' => $foreignMember->id, 'split_type' => 'equal'],
            ],
        ])->assertJsonValidationErrors(['participants.0.household_member_id']);
    }

    /**
     * Verify that transaction updates keep participant validation scoped to their household.
     *
     * @return void This test method does not return a value.
     */
    public function test_cannot_update_transaction_with_participant_from_another_household(): void
    {
        Sanctum::actingAs($this->owner);
        $foreignMember = $this->attacker->householdMemberships()
            ->where('household_id', $this->householdB->id)
            ->firstOrFail();
        $transaction = EconomicTransaction::factory()
            ->forHousehold($this->householdA)
            ->createdBy($this->owner)
            ->shared()
            ->create();

        $this->patchJson("/api/v1/households/{$this->householdA->id}/economy/transactions/{$transaction->id}", [
            'participants' => [
                ['household_member_id' => $foreignMember->id, 'split_type' => 'equal'],
            ],
        ])->assertJsonValidationErrors(['participants.0.household_member_id']);
    }

    /**
     * Verify that import confirmation keeps participant validation scoped to its household.
     *
     * @return void This test method does not return a value.
     */
    public function test_cannot_confirm_import_with_participant_from_another_household(): void
    {
        Sanctum::actingAs($this->owner);
        $foreignMember = $this->attacker->householdMemberships()
            ->where('household_id', $this->householdB->id)
            ->firstOrFail();
        $document = EconomicDocument::factory()
            ->forHousehold($this->householdA)
            ->uploadedBy($this->owner)
            ->create();
        $import = EconomicImport::factory()
            ->forHousehold($this->householdA)
            ->createdBy($this->owner)
            ->extracted([
                'title' => 'Compra compartida',
                'amount' => '10.00',
                'currency' => 'EUR',
            ])
            ->create(['document_id' => $document->id]);

        $this->withHeader('Idempotency-Key', 'foreign-participant-confirm')
            ->postJson("/api/v1/households/{$this->householdA->id}/economy/imports/{$import->id}/confirm", [
                'scope' => 'shared',
                'participants' => [
                    ['household_member_id' => $foreignMember->id, 'split_type' => 'equal'],
                ],
            ])->assertJsonValidationErrors(['participants.0.household_member_id']);
    }

    /**
     * Verify that forbidden responses do not expose transaction payload data.
     *
     * @return void This test method does not return a value.
     */
    public function test_forbidden_responses_return_403_without_payload_of_the_resource(): void
    {
        Sanctum::actingAs($this->attacker);
        $transaction = EconomicTransaction::factory()->forHousehold($this->householdA)->createdBy($this->owner)->shared()->create([
            'title' => 'SECRETO-MUY-CONFIDENCIAL',
        ]);

        $response = $this->getJson("/api/v1/households/{$this->householdB->id}/economy/transactions/{$transaction->id}");
        $response->assertForbidden();

        $this->assertStringNotContainsString('SECRETO-MUY-CONFIDENCIAL', $response->getContent());
    }

    /**
     * Verify that creator filters cannot expose rows from another household.
     *
     * @return void This test method does not return a value.
     */
    public function test_index_endpoints_never_leak_other_household_rows_via_created_by_filter(): void
    {
        Sanctum::actingAs($this->owner);
        EconomicTransaction::factory()->forHousehold($this->householdA)->createdBy($this->owner)->create();

        Sanctum::actingAs($this->attacker);
        $response = $this->getJson("/api/v1/households/{$this->householdB->id}/economy/transactions")
            ->assertOk();

        $this->assertCount(0, $response->json('data'));
    }

    /**
     * Verify that membership in both households does not permit cross-scope transaction URLs.
     *
     * @return void This test method does not return a value.
     */
    public function test_member_of_both_households_cannot_route_transaction_through_another_scope(): void
    {
        HouseholdMember::factory()->create([
            'household_id' => $this->householdA->id,
            'user_id' => $this->attacker->id,
        ]);
        Sanctum::actingAs($this->attacker);

        $transaction = EconomicTransaction::factory()
            ->forHousehold($this->householdA)
            ->createdBy($this->owner)
            ->shared()
            ->create();

        $this->getJson("/api/v1/households/{$this->householdB->id}/economy/transactions/{$transaction->id}")
            ->assertForbidden();
        $this->getJson("/api/v1/economy/me/transactions/{$transaction->id}")
            ->assertForbidden();
    }

    /**
     * Verify that household imports cannot be accessed through another household route.
     *
     * @return void This test method does not return a value.
     */
    public function test_household_import_cannot_be_routed_through_another_household(): void
    {
        HouseholdMember::factory()->create([
            'household_id' => $this->householdA->id,
            'user_id' => $this->attacker->id,
        ]);
        Sanctum::actingAs($this->attacker);

        $import = EconomicImport::factory()
            ->forHousehold($this->householdA)
            ->createdBy($this->owner)
            ->create();

        $this->getJson("/api/v1/households/{$this->householdB->id}/economy/imports/{$import->id}")
            ->assertForbidden();
    }

    /**
     * Verify that household documents cannot be accessed through another household route.
     *
     * @return void This test method does not return a value.
     */
    public function test_household_document_cannot_be_routed_through_another_household(): void
    {
        HouseholdMember::factory()->create([
            'household_id' => $this->householdA->id,
            'user_id' => $this->attacker->id,
        ]);
        Sanctum::actingAs($this->attacker);

        $document = EconomicDocument::factory()
            ->forHousehold($this->householdA)
            ->uploadedBy($this->owner)
            ->create();

        $this->getJson("/api/v1/households/{$this->householdB->id}/economy/documents/{$document->id}")
            ->assertForbidden();
    }
}
