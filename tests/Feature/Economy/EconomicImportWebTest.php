<?php

namespace Tests\Feature\Economy;

use App\Enums\EconomicImportStatus;
use App\Jobs\AnalyzeEconomicDocumentJob;
use App\Models\Contact;
use App\Models\EconomicAccount;
use App\Models\EconomicDocument;
use App\Models\EconomicImport;
use App\Models\EconomicTransaction;
use App\Models\Household;
use App\Models\HouseholdMember;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class EconomicImportWebTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Verify that an authorized user can open the private economic import form.
     *
     * @return void This test method does not return a value.
     */
    public function test_authorized_user_can_open_private_economic_import_form(): void
    {
        $user = $this->authenticateUser();

        $response = $this->actingAs($user)->get(route('economy.me.imports.create'));

        $response->assertOk()->assertInertia(fn (Assert $page): Assert => $page
            ->component('economy/imports/Create')
            ->where('household', null)
        );
    }

    /**
     * Verify that an authorized household member can open the household economic import form.
     *
     * @return void This test method does not return a value.
     */
    public function test_authorized_member_can_open_household_economic_import_form(): void
    {
        $user = $this->authenticateUser();
        $household = Household::factory()->create();
        HouseholdMember::factory()->create([
            'household_id' => $household->id,
            'user_id' => $user->id,
        ]);
        $user->update(['active_household_id' => $household->id]);

        $response = $this->actingAs($user)->get(route('households.economy.imports.create', $household));

        $response->assertOk()->assertInertia(fn (Assert $page): Assert => $page
            ->component('economy/imports/Create')
            ->where('household.id', $household->id)
        );
    }

    /**
     * Verify that the private review page exposes a flat import resource compatible with the Vue component.
     *
     * @return void This test method does not return a value.
     */
    public function test_private_review_page_exposes_flat_import_data(): void
    {
        $user = $this->authenticateUser();
        $document = EconomicDocument::factory()->private()->uploadedBy($user)->create();
        $import = EconomicImport::factory()->private()->createdBy($user)->create([
            'document_id' => $document->id,
        ]);

        $response = $this->actingAs($user)->get(route('economy.me.imports.show', $import));

        $response->assertOk()->assertInertia(fn (Assert $page): Assert => $page
            ->component('economy/imports/Review')
            ->where('importData.id', $import->id)
            ->where('importData.document.id', $document->id)
            ->missing('importData.data')
        );
    }

    /**
     * Verify that an unauthenticated private import submission redirects to the login page.
     *
     * @return void This test method does not return a value.
     */
    public function test_unauthenticated_private_import_submission_redirects_to_login(): void
    {
        $response = $this->post(route('economy.me.imports.store'));

        $response->assertRedirect(route('login'));
        $this->assertDatabaseCount('economic_documents', 0);
        $this->assertDatabaseCount('economic_imports', 0);
    }

    /**
     * Verify that a user without write permission cannot submit a private import.
     *
     * @return void This test method does not return a value.
     */
    public function test_household_reader_can_submit_private_import(): void
    {
        $user = $this->authenticateUser();
        HouseholdMember::factory()->create(['user_id' => $user->id, 'access_profile' => 'reader']);

        $response = $this->actingAs($user)->post(route('economy.me.imports.store'), [
            'file' => UploadedFile::fake()->image('receipt.jpg'),
            'sections' => [
                'place' => true,
                'date' => true,
                'items' => true,
                'taxes' => true,
            ],
        ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();
        $this->assertDatabaseCount('economic_documents', 1);
        $this->assertDatabaseCount('economic_imports', 1);
    }

    /**
     * Verify that the private Inertia submission uploads a document, creates an import, and redirects to review.
     *
     * @return void This test method does not return a value.
     */
    public function test_private_inertia_submission_creates_import_and_redirects_to_review(): void
    {
        $user = $this->authenticateUser();
        Storage::fake('local');
        Queue::fake([AnalyzeEconomicDocumentJob::class]);

        $response = $this->actingAs($user)->post(route('economy.me.imports.store'), [
            'file' => UploadedFile::fake()->image('receipt.jpg', 1200, 800),
            'sections' => [
                'place' => true,
                'date' => false,
                'items' => true,
                'taxes' => false,
            ],
        ]);

        $document = EconomicDocument::query()->sole();
        $import = EconomicImport::query()->sole();

        $response->assertRedirect(route('economy.me.imports.show', $import));

        $this->assertSame($user->id, $document->uploaded_by);
        $this->assertNull($document->household_id);
        Storage::disk('local')->assertExists($document->path);

        $this->assertSame($document->id, $import->document_id);
        $this->assertSame($user->id, $import->created_by);
        $this->assertNull($import->household_id);
        $this->assertCount(4, $import->requested_sections);
        $this->assertTrue($import->requested_sections['place']);
        $this->assertFalse($import->requested_sections['date']);
        $this->assertTrue($import->requested_sections['items']);
        $this->assertFalse($import->requested_sections['taxes']);
        Queue::assertPushed(
            AnalyzeEconomicDocumentJob::class,
            fn (AnalyzeEconomicDocumentJob $job): bool => $job->economicImportId === $import->id,
        );
    }

    /**
     * Verify that the household Inertia submission persists both resources in the routed household scope.
     *
     * @return void This test method does not return a value.
     */
    public function test_household_inertia_submission_creates_scoped_import(): void
    {
        $user = $this->authenticateUser();
        $household = Household::factory()->create();
        HouseholdMember::factory()->create([
            'household_id' => $household->id,
            'user_id' => $user->id,
        ]);
        $user->update(['active_household_id' => $household->id]);
        Storage::fake('local');
        Queue::fake([AnalyzeEconomicDocumentJob::class]);

        $response = $this->actingAs($user)->post(route('households.economy.imports.store', $household), [
            'file' => UploadedFile::fake()->image('household-receipt.jpg', 1200, 800),
            'sections' => [
                'place' => true,
                'date' => true,
                'items' => true,
                'taxes' => true,
            ],
        ]);

        $document = EconomicDocument::query()->sole();
        $import = EconomicImport::query()->sole();

        $response->assertRedirect(route('households.economy.imports.show', [$household, $import]));

        $this->assertSame($household->id, $document->household_id);
        $this->assertSame($household->id, $import->household_id);
        Storage::disk('local')->assertExists($document->path);
        Queue::assertPushed(
            AnalyzeEconomicDocumentJob::class,
            fn (AnalyzeEconomicDocumentJob $job): bool => $job->economicImportId === $import->id,
        );
    }

    /**
     * Verify that an invalid Inertia submission returns localized validation errors without persisting data.
     *
     * @return void This test method does not return a value.
     */
    public function test_private_inertia_submission_rejects_missing_document_and_sections(): void
    {
        $user = $this->authenticateUser();
        Storage::fake('local');
        Queue::fake([AnalyzeEconomicDocumentJob::class]);

        $response = $this->actingAs($user)->post(route('economy.me.imports.store'));

        $response->assertSessionHasErrors([
            'file' => __('validation.required', ['attribute' => __('validation.attributes.file')]),
            'sections' => __('validation.required', ['attribute' => __('validation.attributes.sections')]),
        ]);
        $this->assertDatabaseCount('economic_documents', 0);
        $this->assertDatabaseCount('economic_imports', 0);
        Queue::assertNotPushed(AnalyzeEconomicDocumentJob::class);
    }

    /**
     * Verify that the review page only receives the authenticated user's active accounts.
     *
     * @return void This test method does not return a value.
     */
    public function test_review_page_only_exposes_the_authenticated_users_active_accounts(): void
    {
        $user = $this->authenticateUser();
        $document = EconomicDocument::factory()->private()->uploadedBy($user)->create();
        $import = EconomicImport::factory()->private()->createdBy($user)->create([
            'document_id' => $document->id,
        ]);
        $account = EconomicAccount::factory()->forUser($user)->create(['name' => 'Everyday wallet']);
        EconomicAccount::factory()->forUser($user)->create([
            'name' => 'Archived wallet',
            'archived_at' => now(),
        ]);
        $foreignAccount = EconomicAccount::factory()->create(['name' => 'Foreign wallet']);

        $response = $this->actingAs($user)->get(route('economy.me.imports.show', $import));

        $response->assertOk()->assertInertia(fn (Assert $page): Assert => $page
            ->component('economy/imports/Review')
            ->where('accounts', fn (Collection $accounts): bool => $accounts->pluck('id')->all() === [$account->id])
            ->where('accounts.0.name', 'Everyday wallet')
        );

        $this->assertNotSame($user->id, $foreignAccount->user_id);
    }

    /**
     * Verify that confirming an import persists the selected account on the created transaction.
     *
     * @return void This test method does not return a value.
     */
    public function test_confirming_private_import_persists_the_selected_account(): void
    {
        $user = $this->authenticateUser();
        $document = EconomicDocument::factory()->private()->uploadedBy($user)->create();
        $import = EconomicImport::factory()->private()->createdBy($user)->extracted([
            'title' => 'Groceries',
            'amount' => '12.10',
            'currency' => 'EUR',
        ])->create(['document_id' => $document->id]);
        $account = EconomicAccount::factory()->forUser($user)->create();

        $response = $this->actingAs($user)->post(route('economy.me.imports.confirm', $import), [
            'account_id' => $account->id,
        ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();

        $transaction = EconomicTransaction::query()->sole();

        $this->assertSame($account->id, $transaction->account_id);
        $this->assertSame(EconomicImportStatus::Confirmed, $import->fresh()->status);
    }

    /**
     * Verify that confirming an import rejects an account owned by another user.
     *
     * @return void This test method does not return a value.
     */
    public function test_confirming_private_import_rejects_an_account_of_another_user(): void
    {
        $user = $this->authenticateUser();
        $document = EconomicDocument::factory()->private()->uploadedBy($user)->create();
        $import = EconomicImport::factory()->private()->createdBy($user)->extracted([
            'title' => 'Groceries',
            'amount' => '12.10',
            'currency' => 'EUR',
        ])->create(['document_id' => $document->id]);
        $foreignAccount = EconomicAccount::factory()->create();

        $response = $this->actingAs($user)->post(route('economy.me.imports.confirm', $import), [
            'account_id' => $foreignAccount->id,
        ]);

        $response->assertSessionHasErrors('account_id');
        $this->assertDatabaseCount('economic_transactions', 0);
        $this->assertSame(EconomicImportStatus::ReadyForReview, $import->fresh()->status);
    }

    /**
     * Verify that confirming an import persists the reviewed notes on the created transaction.
     *
     * @return void This test method does not return a value.
     */
    public function test_confirming_private_import_persists_the_reviewed_notes(): void
    {
        $user = $this->authenticateUser();
        $document = EconomicDocument::factory()->private()->uploadedBy($user)->create();
        $import = EconomicImport::factory()->private()->createdBy($user)->extracted([
            'title' => 'Groceries',
            'amount' => '12.10',
            'currency' => 'EUR',
        ])->create(['document_id' => $document->id]);

        $response = $this->actingAs($user)->post(route('economy.me.imports.confirm', $import), [
            'notes' => 'Weekly grocery run for the family',
        ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();

        $transaction = EconomicTransaction::query()->sole();

        $this->assertSame('Weekly grocery run for the family', $transaction->notes);
        $this->assertSame(EconomicImportStatus::Confirmed, $import->fresh()->status);
    }

    /**
     * Verify that confirming an import rejects notes longer than the allowed length.
     *
     * @return void This test method does not return a value.
     */
    public function test_confirming_private_import_rejects_notes_longer_than_the_limit(): void
    {
        $user = $this->authenticateUser();
        $document = EconomicDocument::factory()->private()->uploadedBy($user)->create();
        $import = EconomicImport::factory()->private()->createdBy($user)->extracted([
            'title' => 'Groceries',
            'amount' => '12.10',
            'currency' => 'EUR',
        ])->create(['document_id' => $document->id]);

        $response = $this->actingAs($user)->post(route('economy.me.imports.confirm', $import), [
            'notes' => str_repeat('a', 1001),
        ]);

        $response->assertSessionHasErrors('notes');
        $this->assertDatabaseCount('economic_transactions', 0);
        $this->assertSame(EconomicImportStatus::ReadyForReview, $import->fresh()->status);
    }

    /**
     * Verify that an empty reviewed notes value is normalized to null instead of an empty string.
     *
     * @return void This test method does not return a value.
     */
    public function test_confirming_private_import_with_empty_notes_persists_null(): void
    {
        $user = $this->authenticateUser();
        $document = EconomicDocument::factory()->private()->uploadedBy($user)->create();
        $import = EconomicImport::factory()->private()->createdBy($user)->extracted([
            'title' => 'Groceries',
            'amount' => '12.10',
            'currency' => 'EUR',
        ])->create(['document_id' => $document->id]);

        $response = $this->actingAs($user)->post(route('economy.me.imports.confirm', $import), [
            'notes' => '',
        ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();

        $transaction = EconomicTransaction::query()->sole();

        $this->assertNull($transaction->notes);
    }

    /**
     * Verify that confirming an import links a contact visible to the user to the created transaction.
     *
     * @return void This test method does not return a value.
     */
    public function test_confirming_private_import_links_a_visible_contact(): void
    {
        $user = $this->authenticateUser();
        $document = EconomicDocument::factory()->private()->uploadedBy($user)->create();
        $import = EconomicImport::factory()->private()->createdBy($user)->extracted([
            'title' => 'Groceries',
            'amount' => '12.10',
            'currency' => 'EUR',
        ])->create(['document_id' => $document->id]);
        $contact = Contact::create([
            'user_id' => $user->id,
            'type' => 'person',
            'display_name' => 'Ana',
        ]);

        $response = $this->actingAs($user)->post(route('economy.me.imports.confirm', $import), [
            'contact_id' => $contact->id,
        ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();

        $transaction = EconomicTransaction::query()->sole();
        $transaction->load('contacts');

        $this->assertSame([$contact->id], $transaction->contacts->pluck('id')->all());
        $this->assertSame('related', $transaction->contacts->first()?->pivot->role);
        $this->assertSame(EconomicImportStatus::Confirmed, $import->fresh()->status);
    }

    /**
     * Verify that confirming an import rejects a contact that is not visible to the user.
     *
     * @return void This test method does not return a value.
     */
    public function test_confirming_private_import_rejects_a_contact_of_another_user(): void
    {
        $user = $this->authenticateUser();
        $document = EconomicDocument::factory()->private()->uploadedBy($user)->create();
        $import = EconomicImport::factory()->private()->createdBy($user)->extracted([
            'title' => 'Groceries',
            'amount' => '12.10',
            'currency' => 'EUR',
        ])->create(['document_id' => $document->id]);
        $foreignContact = Contact::create([
            'user_id' => User::factory()->create()->id,
            'type' => 'person',
            'display_name' => 'Ajeno',
        ]);

        $response = $this->actingAs($user)->post(route('economy.me.imports.confirm', $import), [
            'contact_id' => $foreignContact->id,
        ]);

        $response->assertSessionHasErrors('contact_id');
        $this->assertDatabaseCount('economic_transactions', 0);
        $this->assertDatabaseCount('economic_transaction_contacts', 0);
        $this->assertSame(EconomicImportStatus::ReadyForReview, $import->fresh()->status);
    }

    /**
     * Verify that confirming an import without a contact leaves the transaction unlinked.
     *
     * @return void This test method does not return a value.
     */
    public function test_confirming_private_import_without_a_contact_links_none(): void
    {
        $user = $this->authenticateUser();
        $document = EconomicDocument::factory()->private()->uploadedBy($user)->create();
        $import = EconomicImport::factory()->private()->createdBy($user)->extracted([
            'title' => 'Groceries',
            'amount' => '12.10',
            'currency' => 'EUR',
        ])->create(['document_id' => $document->id]);

        $response = $this->actingAs($user)->post(route('economy.me.imports.confirm', $import));

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();

        $transaction = EconomicTransaction::query()->sole();
        $transaction->load('contacts');

        $this->assertCount(0, $transaction->contacts);
        $this->assertDatabaseCount('economic_transaction_contacts', 0);
        $this->assertSame(EconomicImportStatus::Confirmed, $import->fresh()->status);
    }

    /**
     * Create a user with the requested application role.
     *
     * @param  string  $role  The role slug that defines the user's route permissions.
     * @return User The user configured for an authenticated web request.
     */
    private function authenticateUser(string $role = 'user'): User
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }
}
