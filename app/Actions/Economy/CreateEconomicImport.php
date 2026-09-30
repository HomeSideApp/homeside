<?php

namespace App\Actions\Economy;

use App\Data\Economy\CreateEconomicImportData;
use App\Enums\EconomicImportStatus;
use App\Jobs\AnalyzeEconomicDocumentJob;
use App\Models\EconomicDocument;
use App\Models\EconomicImport;
use App\Models\Household;
use App\Models\User;

/**
 * Creates an economic import for the user's document and dispatches the
 * analysis job.
 */
final class CreateEconomicImport
{
    /**
     * Create an economic import and enqueue its document analysis.
     *
     * @param  CreateEconomicImportData  $data  The document and requested sections for the import.
     * @param  Household|null  $household  The household scope, or null for a private import.
     * @param  User  $user  The user creating the import.
     * @return EconomicImport The pending import that was persisted and queued for analysis.
     */
    public function execute(
        CreateEconomicImportData $data,
        ?Household $household,
        User $user,
    ): EconomicImport {
        $document = EconomicDocument::query()
            ->whereKey($data->document_id)
            ->where('household_id', $household?->id)
            ->when($household === null, fn ($query) => $query->where('uploaded_by', $user->id))
            ->firstOrFail();

        $import = EconomicImport::create([
            'household_id' => $household?->id,
            'document_id' => $document->id,
            'created_by' => $user->id,
            'status' => EconomicImportStatus::Pending,
            'requested_sections' => $data->requested_sections,
        ]);

        AnalyzeEconomicDocumentJob::dispatch($import->id);

        return $import;
    }
}
