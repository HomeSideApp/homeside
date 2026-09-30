<?php

namespace App\Actions\Economy;

use App\Enums\EconomicImportStatus;
use App\Jobs\AnalyzeEconomicDocumentJob;
use App\Models\EconomicImport;

/**
 * Resets a failed import back to pending and dispatches the analysis job
 * again.
 */
final class RetryEconomicImport
{
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

        AnalyzeEconomicDocumentJob::dispatch($import->id);

        return $import->fresh();
    }
}
