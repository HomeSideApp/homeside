<?php

namespace App\Jobs;

use App\Enums\EconomicImportStatus;
use App\Exceptions\IncompleteAnalysisException;
use App\Models\EconomicImport;
use App\Services\Economy\TicketAnalyzer;
use HomeSide\AiAgents\Exceptions\NoAiProviderException;
use HomeSide\AiAgents\Execution\AiExecutionContextData;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Laravel\Ai\Exceptions\InsufficientCreditsException;
use Laravel\Ai\Exceptions\ProviderConnectionException;
use Laravel\Ai\Exceptions\ProviderOverloadedException;
use Laravel\Ai\Exceptions\RateLimitedException;
use RuntimeException;

class AnalyzeEconomicDocumentJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 180;

    /** @var array<int, int> */
    public array $backoff = [30, 60, 120];

    /**
     * Create a queued document analysis job.
     *
     * @param  string  $economicImportId  The identifier of the import to analyze.
     * @return void This constructor does not return a value.
     */
    public function __construct(
        public string $economicImportId,
    ) {}

    /**
     * Analyze the import document and persist either its extracted payload or failure state.
     *
     * @param  TicketAnalyzer  $analyzer  The service that performs structured receipt analysis.
     * @return void This job handler does not return a value.
     */
    public function handle(TicketAnalyzer $analyzer): void
    {
        $import = EconomicImport::with(['document', 'creator'])->findOrFail($this->economicImportId);

        if ($import->status !== EconomicImportStatus::Pending) {
            return;
        }

        $claimed = EconomicImport::where('id', $import->id)
            ->where('status', EconomicImportStatus::Pending)
            ->update([
                'status' => EconomicImportStatus::Processing,
                'started_at' => now(),
            ]);

        if (! $claimed) {
            return;
        }

        $import->refresh();

        $context = new AiExecutionContextData(
            userId: $import->created_by,
            tenantId: $import->household_id,
        );

        try {
            $result = $analyzer->analyze(
                disk: $import->document->disk,
                storedPath: $import->document->ai_path ?? $import->document->path,
                mimeType: $import->document->mime_type,
                sections: $import->requested_sections,
                context: $context,
            );

            $import->update([
                'status' => EconomicImportStatus::ReadyForReview,
                'extracted_payload' => $result->data->toArray(),
                'ai_run_id' => $result->runId,
                'finished_at' => now(),
            ]);
        } catch (IncompleteAnalysisException $e) {
            $import->update([
                'status' => EconomicImportStatus::Failed,
                'error_code' => 'incomplete_response',
                'error_message' => $e->getMessage(),
                'finished_at' => now(),
            ]);
        } catch (RuntimeException $e) {
            $this->failFromProvider($import, $e);
        }
    }

    /**
     * Map an AI provider exception to a stable error code and retry policy.
     *
     * @param  EconomicImport  $import  The import whose failure state must be persisted.
     * @param  RuntimeException  $e  The wrapped provider exception to classify.
     * @return void This failure classifier does not return a value.
     */
    private function failFromProvider(EconomicImport $import, RuntimeException $e): void
    {
        $previous = $e->getPrevious();

        $code = match (true) {
            $previous instanceof RateLimitedException => 'rate_limit',
            $previous instanceof ProviderOverloadedException => 'server_error',
            $previous instanceof ProviderConnectionException => 'connection',
            $previous instanceof InsufficientCreditsException => 'no_credits',
            $e instanceof NoAiProviderException => 'no_provider',
            str_contains(strtolower($e->getMessage()), 'timeout') => 'timeout',
            default => 'provider_error',
        };

        $import->update([
            'status' => EconomicImportStatus::Failed,
            'error_code' => $code,
            'error_message' => match ($code) {
                'rate_limit' => __('app.economy.analysis.rate_limit'),
                'server_error' => __('app.economy.analysis.server_error'),
                'connection' => __('app.economy.analysis.connection'),
                'no_credits' => __('app.economy.analysis.no_credits'),
                'no_provider' => __('app.economy.analysis.no_provider'),
                'timeout' => __('app.economy.analysis.timeout'),
                default => __('app.economy.analysis.provider_error', ['message' => $e->getMessage()]),
            },
            'finished_at' => now(),
        ]);

        if (in_array($code, ['rate_limit', 'server_error', 'connection'], true)) {
            throw $e;
        }
    }

    /**
     * Mark an import as failed after the queue exhausts all analysis attempts.
     *
     * @param  \Throwable  $exception  The final exception reported by the queue worker.
     * @return void This terminal failure handler does not return a value.
     */
    public function failed(\Throwable $exception): void
    {
        $import = EconomicImport::find($this->economicImportId);

        if ($import && $import->status === EconomicImportStatus::Processing) {
            $import->update([
                'status' => EconomicImportStatus::Failed,
                'error_code' => 'job_failed',
                'error_message' => __('app.economy.analysis.job_failed'),
                'finished_at' => now(),
            ]);
        }
    }
}
