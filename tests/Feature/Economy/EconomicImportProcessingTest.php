<?php

namespace Tests\Feature\Economy;

use App\Data\Economy\CropRectData;
use App\Data\Economy\ImageProcessingData;
use App\Enums\EconomicImportStatus;
use App\Jobs\AnalyzeEconomicDocumentJob;
use App\Models\EconomicDocument;
use App\Models\EconomicImport;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class EconomicImportProcessingTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        Queue::fake();
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->user = User::factory()->create();
        $this->user->assignRole('user');
    }

    public function test_upload_with_processing_generates_a_cropped_ai_version(): void
    {
        $response = $this->actingAs($this->user)->post(route('economy.me.imports.store'), [
            'file' => UploadedFile::fake()->image('ticket.jpg', 1000, 800),
            'sections' => ['place' => true, 'date' => true, 'items' => true, 'taxes' => true],
            'image_processing' => [
                'crop' => ['x' => 0.1, 'y' => 0.1, 'width' => 0.5, 'height' => 0.5],
                'rotate' => 90,
                'brightness' => 20,
            ],
        ]);

        $response->assertRedirect();

        $document = EconomicDocument::query()->firstOrFail();

        $this->assertNotNull($document->ai_path);
        $this->assertNotSame($document->path, $document->ai_path);
        $this->assertSame(0.5, $document->processing_meta['crop']['width']);
        $this->assertSame(90, $document->processing_meta['rotate']);
        $this->assertSame(20, $document->processing_meta['brightness']);
    }

    public function test_upload_without_processing_keeps_the_original_behaviour(): void
    {
        $this->actingAs($this->user)->post(route('economy.me.imports.store'), [
            'file' => UploadedFile::fake()->image('ticket.jpg', 1000, 800),
            'sections' => ['place' => true, 'date' => true, 'items' => true, 'taxes' => true],
        ])->assertRedirect();

        $document = EconomicDocument::query()->firstOrFail();

        $this->assertNotNull($document->ai_path);
        $this->assertNull($document->processing_meta);
    }

    public function test_invalid_processing_is_rejected(): void
    {
        $this->actingAs($this->user)->post(route('economy.me.imports.store'), [
            'file' => UploadedFile::fake()->image('ticket.jpg'),
            'sections' => ['place' => true, 'date' => true, 'items' => true, 'taxes' => true],
            'image_processing' => [
                'crop' => ['x' => 0.9, 'y' => 0, 'width' => 0.5, 'height' => 0.5],
            ],
        ])->assertSessionHasErrors('image_processing');

        $this->actingAs($this->user)->post(route('economy.me.imports.store'), [
            'file' => UploadedFile::fake()->image('ticket.jpg'),
            'sections' => ['place' => true, 'date' => true, 'items' => true, 'taxes' => true],
            'image_processing' => ['rotate' => 45],
        ])->assertSessionHasErrors('image_processing.rotate');
    }

    public function test_processing_data_validates_normalized_bounds(): void
    {
        $this->expectException(ValidationException::class);

        (new CropRectData(0.9, 0.9, 0.5, 0.5))->assertValid();
    }

    public function test_processing_data_detects_a_noop_payload(): void
    {
        $this->assertTrue((new ImageProcessingData)->isNoop());
        $this->assertFalse((new ImageProcessingData(rotate: 90))->isNoop());
    }

    public function test_reprocess_regenerates_the_ai_version_and_restarts_the_analysis(): void
    {
        $import = $this->createReadyImport();

        $this->actingAs($this->user)
            ->post(route('economy.me.imports.reprocess', $import), [
                'image_processing' => [
                    'crop' => ['x' => 0, 'y' => 0, 'width' => 0.8, 'height' => 0.8],
                ],
            ])
            ->assertRedirect();

        $document = $import->refresh()->document;
        $import->refresh();

        $this->assertSame(EconomicImportStatus::Pending, $import->status);
        $this->assertNull($import->extracted_payload);
        $this->assertSame(0.8, $document->processing_meta['crop']['width']);
        Queue::assertPushed(AnalyzeEconomicDocumentJob::class);
    }

    public function test_reprocess_rejects_a_pdf_document(): void
    {
        $document = EconomicDocument::factory()->create([
            'uploaded_by' => $this->user->id,
            'household_id' => null,
            'mime_type' => 'application/pdf',
        ]);
        $import = EconomicImport::factory()->create([
            'document_id' => $document->id,
            'created_by' => $this->user->id,
            'household_id' => null,
            'status' => EconomicImportStatus::ReadyForReview,
        ]);

        $this->actingAs($this->user)
            ->post(route('economy.me.imports.reprocess', $import), [
                'image_processing' => ['brightness' => 10],
            ])
            ->assertSessionHasErrors('image_processing');

        $this->assertSame(EconomicImportStatus::ReadyForReview, $import->refresh()->status);
    }

    public function test_user_cannot_reprocess_another_users_import(): void
    {
        $import = $this->createReadyImport();
        $intruder = User::factory()->create();
        $intruder->assignRole('user');

        $this->actingAs($intruder)
            ->post(route('economy.me.imports.reprocess', $import), [
                'image_processing' => ['brightness' => 10],
            ])
            ->assertForbidden();
    }

    /**
     * Create a private import ready for review backed by a stored image.
     *
     * @return EconomicImport The import ready to be reprocessed.
     */
    private function createReadyImport(): EconomicImport
    {
        $path = 'economy/documents/user-'.$this->user->id.'/original.jpg';
        Storage::disk('local')->put($path, UploadedFile::fake()->image('ticket.jpg', 900, 700)->get());

        $document = EconomicDocument::factory()->create([
            'uploaded_by' => $this->user->id,
            'household_id' => null,
            'disk' => 'local',
            'path' => $path,
            'mime_type' => 'image/jpeg',
            'ai_path' => $path,
        ]);

        $import = EconomicImport::factory()->create([
            'document_id' => $document->id,
            'created_by' => $this->user->id,
            'household_id' => null,
            'status' => EconomicImportStatus::ReadyForReview,
            'extracted_payload' => ['title' => 'Ticket', 'amount' => '10.00'],
        ]);

        return $import;
    }
}
