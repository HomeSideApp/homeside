<?php

namespace Tests\Feature\Jobs;

use App\Actions\Economy\RetryEconomicImport;
use App\Data\Economy\ReceiptAnalysisResultData;
use App\Enums\EconomicImportStatus;
use App\Exceptions\IncompleteAnalysisException;
use App\Jobs\AnalyzeEconomicDocumentJob;
use App\Models\EconomicImport;
use App\Services\Economy\TicketAnalysisResult;
use App\Services\Economy\TicketAnalyzer;
use HomeSide\AiAgents\Exceptions\NoAiProviderException;
use HomeSide\AiAgents\Models\AiRun;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AnalyzeEconomicDocumentJobTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Verify that a successful job stores extracted data and its recorded AI run.
     *
     * @return void This test method does not return a value.
     */
    public function test_job_changes_status_to_processing_then_ready_for_review(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('economy/documents/ticket.jpg', 'fake-image');

        $import = EconomicImport::factory()->create();

        // The AI run must exist because the import stores its foreign identifier.
        $run = AiRun::create([
            'user_id' => $import->created_by,
            'household_id' => $import->household_id,
            'agent' => 'economy.ticket_analyzer',
            'agent_version' => 1,
            'status' => 'ok',
            'duration_ms' => 100,
        ]);

        $this->mock(TicketAnalyzer::class, function ($mock) use ($run) {
            $mock->shouldReceive('analyze')->once()->andReturn(
                new TicketAnalysisResult(
                    data: ReceiptAnalysisResultData::fromArray([
                        'title' => 'Mercadona',
                        'amount' => '42.73',
                        'currency' => 'EUR',
                    ]),
                    runId: $run->id,
                )
            );
        });

        (new AnalyzeEconomicDocumentJob($import->id))->handle(app(TicketAnalyzer::class));

        $import->refresh();

        $this->assertSame(EconomicImportStatus::ReadyForReview, $import->status);
        $this->assertSame('42.73', $import->extracted_payload['amount']);
        $this->assertSame($run->id, $import->ai_run_id);
    }

    /**
     * Verify that incomplete structured output fails an import without scheduling a retry.
     *
     * @return void This test method does not return a value.
     */
    public function test_job_marks_incomplete_response_without_retry(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('economy/documents/ticket.jpg', 'fake-image');

        $import = EconomicImport::factory()->create();

        $this->mock(TicketAnalyzer::class, function ($mock) {
            $mock->shouldReceive('analyze')->once()->andThrow(
                new IncompleteAnalysisException(__('app.economy.analysis.incomplete_response'))
            );
        });

        (new AnalyzeEconomicDocumentJob($import->id))->handle(app(TicketAnalyzer::class));

        $import->refresh();

        $this->assertSame(EconomicImportStatus::Failed, $import->status);
        $this->assertSame('incomplete_response', $import->error_code);
    }

    /**
     * Verify that a missing provider is classified with the stable no-provider error code.
     *
     * @return void This test method does not return a value.
     */
    public function test_job_marks_no_provider_when_provider_resolver_returns_none(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('economy/documents/ticket.jpg', 'fake-image');

        $import = EconomicImport::factory()->private()->create();

        $this->mock(TicketAnalyzer::class, function ($mock) {
            $mock->shouldReceive('analyze')->once()->andThrow(
                new NoAiProviderException(__('app.errors.no_provider_for_module', ['module' => 'economy']))
            );
        });

        (new AnalyzeEconomicDocumentJob($import->id))->handle(app(TicketAnalyzer::class));

        $import->refresh();

        $this->assertSame(EconomicImportStatus::Failed, $import->status);
        $this->assertSame('no_provider', $import->error_code);
    }

    /**
     * Verify that jobs skip imports which are no longer pending.
     *
     * @return void This test method does not return a value.
     */
    public function test_job_is_idempotent_when_not_pending(): void
    {
        $import = EconomicImport::factory()->status(EconomicImportStatus::Confirmed)->create();

        // A completed import must not invoke the analyzer or change state.
        $this->mock(TicketAnalyzer::class, function ($mock) {
            $mock->shouldNotReceive('analyze');
        });

        (new AnalyzeEconomicDocumentJob($import->id))->handle(app(TicketAnalyzer::class));

        $this->assertSame(EconomicImportStatus::Confirmed, $import->fresh()->status);
    }

    /**
     * Verify that retrying an import dispatches another document analysis job.
     *
     * @return void This test method does not return a value.
     */
    public function test_job_dispatches_via_action(): void
    {
        Queue::fake();

        $import = EconomicImport::factory()->status(EconomicImportStatus::Failed)->create();

        (new RetryEconomicImport)->execute($import->fresh());

        Queue::assertPushed(AnalyzeEconomicDocumentJob::class);
    }
}
