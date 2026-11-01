<?php

namespace App\Jobs;

use App\Contracts\EntitlementGate;
use App\Data\Economy\ReceiptAnalysisResultData;
use App\Enums\EconomicImportStatus;
use App\Exceptions\EntitlementQuotaExceededException;
use App\Exceptions\IncompleteAnalysisException;
use App\Models\EconomicImport;
use App\Models\User;
use App\Services\Economy\TicketAnalyzer;
use HomeSide\AiAgents\Exceptions\NoAiProviderException;
use HomeSide\AiAgents\Execution\AiExecutionContextData;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Laravel\Ai\Exceptions\InsufficientCreditsException;
use Laravel\Ai\Exceptions\ProviderConnectionException;
use Laravel\Ai\Exceptions\ProviderOverloadedException;
use Laravel\Ai\Exceptions\RateLimitedException;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

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
     * @param  string|null  $operationId  The usage operation id reserved for the metered AI attempt, when one was reserved before dispatch.
     * @return void This constructor does not return a value.
     */
    public function __construct(
        public string $economicImportId,
        public ?string $operationId = null,
    ) {}

    /**
     * Analyze the import document, offline first and metered AI only when the offline layer cannot read it.
     *
     * @param  TicketAnalyzer  $analyzer  The service that performs offline and AI receipt analysis.
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

        $gate = app(EntitlementGate::class);

        $deterministic = $this->analyzeOffline($analyzer, $import);

        if ($deterministic !== null) {
            // A dispatcher that still meters before dispatch (reprocess and retry) may have
            // reserved a credit for an analysis the offline layer just made unnecessary.
            $this->releaseUsageReservation($gate);

            // Offline reading is free: the import becomes reviewable without reserving or
            // consuming a recognition credit, and without an AI run to point at.
            $import->update([
                'status' => EconomicImportStatus::ReadyForReview,
                'extracted_payload' => $deterministic->toArray(),
                'ai_run_id' => null,
                'finished_at' => now(),
            ]);

            return;
        }

        $reservation = $this->reserveRecognition($gate, $import);

        if ($reservation['status'] === 'denied') {
            $import->update([
                'status' => EconomicImportStatus::Failed,
                'error_code' => $reservation['errorCode'],
                'error_message' => $reservation['errorMessage'],
                'finished_at' => now(),
            ]);

            return;
        }

        $operationId = $reservation['operationId'];
        $this->operationId = $operationId;

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

            // Persist the reserved usage credit for this analysis attempt.
            if ($operationId !== null) {
                $gate->commitUsage($operationId);
            }
        } catch (IncompleteAnalysisException $e) {
            $this->releaseUsageReservation($gate);

            $import->update([
                'status' => EconomicImportStatus::Failed,
                'error_code' => 'incomplete_response',
                'error_message' => $e->getMessage(),
                'finished_at' => now(),
            ]);
        } catch (RuntimeException $e) {
            $this->releaseUsageReservation($gate);

            $this->failFromProvider($import, $e);
        }
    }

    /**
     * Try the offline layer on the document selected for analysis.
     *
     * The offline layer is an optimisation, never a new failure mode: when it cannot read
     * the document it returns null and the metered AI path takes over. That includes an
     * unexpected error inside the layer, which is reported and then ignored for the same
     * reason — a user must not lose an import because the free first pass broke.
     *
     * @param  TicketAnalyzer  $analyzer  The service that performs offline receipt analysis.
     * @param  EconomicImport  $import  The import whose document must be read.
     * @return ReceiptAnalysisResultData|null The offline result, or null when the AI path must run.
     */
    private function analyzeOffline(TicketAnalyzer $analyzer, EconomicImport $import): ?ReceiptAnalysisResultData
    {
        $document = $import->document;

        if ($document === null) {
            return null;
        }

        $storedPath = $document->ai_path ?? $document->path;

        if ($storedPath === null || $storedPath === '') {
            return null;
        }

        $storage = Storage::disk($document->disk);

        if (! $storage->exists($storedPath)) {
            return null;
        }

        try {
            return $analyzer->analyzeDeterministically($storage->path($storedPath));
        } catch (Throwable $exception) {
            report($exception);

            return null;
        }
    }

    /**
     * Reserve one recognition credit for the metered AI attempt.
     *
     * A reservation made by the dispatcher (reprocess and retry still meter before
     * dispatch) is reused instead of reserving twice. Only an import dispatched without
     * a reservation — the upload path, which no longer meters — reserves here, and only
     * when the free offline pass could not read the ticket.
     *
     * @param  EntitlementGate  $gate  The entitlement gate that owns the reservation.
     * @param  EconomicImport  $import  The import that is about to consume the credit.
     * @return array{status: 'reserved'|'unmetered'|'denied', operationId: string|null, errorCode?: string, errorMessage?: string} The reservation outcome and, when denied, the failure to persist.
     */
    private function reserveRecognition(EntitlementGate $gate, EconomicImport $import): array
    {
        if ($this->operationId !== null) {
            return ['status' => 'reserved', 'operationId' => $this->operationId];
        }

        $actor = $import->creator;

        if (! $actor instanceof User) {
            // Nothing to meter against: run the metered path unmetered rather than deny.
            return ['status' => 'unmetered', 'operationId' => null];
        }

        try {
            $operationId = $gate->reserveTicketRecognition($actor, $import->id);
        } catch (EntitlementQuotaExceededException) {
            return [
                'status' => 'denied',
                'operationId' => null,
                'errorCode' => 'no_credits',
                'errorMessage' => __('app.economy.analysis.no_credits'),
            ];
        } catch (HttpExceptionInterface $exception) {
            return [
                'status' => 'denied',
                'operationId' => null,
                'errorCode' => 'entitlement_denied',
                'errorMessage' => $exception->getMessage(),
            ];
        }

        return ['status' => 'reserved', 'operationId' => $operationId];
    }

    /**
     * The SaaS usage operation id for this analysis attempt. The dispatcher
     * passes the reserved operation id; the default derives the first attempt
     * deterministically from the import id.
     */
    private function usageOperationId(): string
    {
        return $this->operationId ?? "ticket:{$this->economicImportId}:1";
    }

    /**
     * Free a reserved usage credit. Committing or releasing twice must stay silent no-ops,
     * so a reservation the dispatcher made can be dropped without knowing who made it.
     */
    private function releaseUsageReservation(EntitlementGate $gate): void
    {
        $operationId = $this->operationId;

        if ($operationId === null) {
            return;
        }

        $this->operationId = null;

        try {
            $gate->releaseUsage($operationId);
        } catch (RuntimeException) {
            // Already committed or already released — nothing is owed.
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
     * @param  Throwable  $exception  The final exception reported by the queue worker.
     * @return void This terminal failure handler does not return a value.
     */
    public function failed(Throwable $exception): void
    {
        // Free any still-reserved credit; a committed or released entry is a
        // silent no-op on the gate.
        try {
            app(EntitlementGate::class)->releaseUsage($this->usageOperationId());
        } catch (RuntimeException) {
            // Already committed — the credit was legitimately consumed.
        }

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
