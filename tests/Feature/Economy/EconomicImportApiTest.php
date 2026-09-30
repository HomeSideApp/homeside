<?php

namespace Tests\Feature\Economy;

use App\Actions\Economy\UploadEconomicDocument;
use App\Enums\EconomicImportStatus;
use App\Models\EconomicDocument;
use App\Models\EconomicImport;
use App\Models\EconomicTransaction;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class EconomicImportApiTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    /**
     * Prepare an authenticated user and an isolated queue for each import test.
     *
     * @return void This setup method does not return a value.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->user = User::factory()->create();
        $this->user->assignRole('user');
        Sanctum::actingAs($this->user);
        Queue::fake();
    }

    /**
     * Verify that an authenticated user can start an import outside a household.
     *
     * @return void This test method does not return a value.
     */
    public function test_user_can_create_private_import_without_a_household(): void
    {
        $document = EconomicDocument::factory()->private()->uploadedBy($this->user)->create();

        $this->postJson('/api/v1/economy/me/imports', [
            'document_id' => $document->id,
            'sections' => ['place' => true, 'date' => true, 'items' => true, 'taxes' => true],
        ])->assertCreated()
            ->assertJsonPath('data.status', 'queued')
            ->assertJsonPath('data.scope.household_id', null);
    }

    /**
     * Verify that confirming a private import persists its transaction, items, and taxes.
     *
     * @return void This test method does not return a value.
     */
    public function test_confirm_private_import_creates_a_private_transaction_with_items_and_taxes(): void
    {
        $document = EconomicDocument::factory()->private()->uploadedBy($this->user)->create();
        $import = EconomicImport::factory()->private()->createdBy($this->user)->extracted([
            'title' => 'Compra',
            'amount' => '12.10',
            'currency' => 'EUR',
            'items' => [['name' => 'Pan', 'quantity' => 1, 'unit_amount' => '2.10', 'total' => '2.10']],
            'taxes' => [['name' => 'IVA', 'rate' => '10', 'taxable_base' => '11.00', 'amount' => '1.10']],
        ])->create(['document_id' => $document->id]);

        $response = $this->withHeader('Idempotency-Key', 'confirm-private-import')
            ->postJson("/api/v1/economy/me/imports/{$import->id}/confirm", [
                'title' => 'Compra revisada',
                'participants' => [],
            ])->assertCreated();

        $transactionId = $response->json('data.id');
        $this->assertDatabaseHas('economic_transactions', [
            'id' => $transactionId,
            'household_id' => null,
            'source_document_id' => $document->id,
            'amount_minor' => 1210,
        ]);
        $this->assertDatabaseHas('economic_transaction_items', ['transaction_id' => $transactionId, 'total_minor' => 210]);
        $this->assertDatabaseHas('economic_transaction_taxes', ['transaction_id' => $transactionId, 'tax_amount_minor' => 110]);
        $this->assertSame(EconomicImportStatus::Confirmed, $import->fresh()->status);
        $this->assertInstanceOf(EconomicTransaction::class, EconomicTransaction::find($transactionId));
    }

    /**
     * Verify that private document deduplication never returns another user's document.
     *
     * @return void This test method does not return a value.
     */
    public function test_private_document_deduplication_is_scoped_to_the_uploader(): void
    {
        Storage::fake('local');
        $otherUser = User::factory()->create();
        $file = UploadedFile::fake()->image('ticket.jpg');

        EconomicDocument::factory()->private()->uploadedBy($otherUser)->create([
            'sha256' => hash_file('sha256', $file->getRealPath()),
        ]);

        $document = app(UploadEconomicDocument::class)->execute($file, null, $this->user);

        $this->assertSame($this->user->id, $document->uploaded_by);
        $this->assertNotSame($otherUser->id, $document->uploaded_by);
    }

    public function test_user_can_download_private_document_through_api(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('economy/documents/ticket.jpg', 'image-content');
        $document = EconomicDocument::factory()->private()->uploadedBy($this->user)->create([
            'path' => 'economy/documents/ticket.jpg',
            'original_filename' => 'ticket.jpg',
        ]);

        $this->getJson(route('api.v1.economy.me.documents.show', $document))
            ->assertOk()
            ->assertJsonPath(
                'data.file_url',
                route('api.v1.economy.me.documents.file', $document),
            );

        $this->get(route('api.v1.economy.me.documents.file', $document))
            ->assertOk()
            ->assertDownload('ticket.jpg');
    }

    /**
     * Verify that confirmation can explicitly clear optional values extracted by the AI.
     *
     * @return void This test method does not return a value.
     */
    public function test_confirm_import_can_clear_extracted_optional_values(): void
    {
        $document = EconomicDocument::factory()->private()->uploadedBy($this->user)->create();
        $import = EconomicImport::factory()->private()->createdBy($this->user)->extracted([
            'title' => 'Purchase',
            'amount' => '10.00',
            'currency' => 'EUR',
            'place' => 'Extracted place',
            'occurred_at' => now()->toISOString(),
        ])->create(['document_id' => $document->id]);

        $response = $this->withHeader('Idempotency-Key', 'confirm-clear-values')
            ->postJson("/api/v1/economy/me/imports/{$import->id}/confirm", [
                'place' => null,
                'occurred_at' => null,
            ])->assertCreated();

        $transaction = EconomicTransaction::query()->findOrFail($response->json('data.id'));
        $this->assertNull($transaction->place);
        $this->assertNull($transaction->occurred_at);
    }
}
