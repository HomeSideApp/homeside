<?php

namespace App\Actions\Economy;

use App\Contracts\EntitlementGate;
use App\Enums\EconomicImportStatus;
use App\Jobs\AnalyzeEconomicDocumentJob;
use App\Models\EconomicImport;
use App\Models\User;

/**
 * Resets a failed import back to pending and dispatches the analysis job
 * again.
 */
final class RetryEconomicImport
{
    public function __construct(
        private readonly EntitlementGate $entitlements,
    ) {}

    /**
     * Reset a failed economic import and enqueue another analysis attempt.
     *
     * @param  EconomicImport  $import  The failed import to retry.
     * @return EconomicImport The refreshed import after it has returned to the pending state.
     */
    public function execute(EconomicImport $import): EconomicImport
    {
        if ($import->status !== EconomicImportStatus::Failed) {
            abort(409, __('app.validation.only_failed_imports_retryable'));
        }

        $import->update([
            'status' => EconomicImportStatus::Pending,
            'error_code' => null,
            'error_message' => null,
        ]);

        // A manual retry is a fresh analysis attempt: it meters a new credit
        // (inert while SaaS disabled). AI consumption belongs to the user
        // initiating the operation, falling back to the import creator when
        // the action runs outside an authenticated session. When no user is
        // resolvable at all, skip metering entirely and let the job run with
        // a null operationId as designed — never reserve against a null user.
        $actor = auth()->user() ?? $import->creator;

        $operationId = $actor instanceof User
            ? $this->entitlements->reserveTicketRecognition($actor, $import->id)
            : null;

        AnalyzeEconomicDocumentJob::dispatch($import->id, $operationId);

        return $import->fresh();
    }
}
