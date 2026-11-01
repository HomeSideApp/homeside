<?php

namespace App\Actions\Economy;

use App\Contracts\EntitlementGate;
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
     * The entitlement port stays injected even though uploading no longer meters: the
     * credit moved to the analysis job, which only spends it when the offline pass cannot
     * read the ticket. Keeping the dependency keeps this consumer on the app-owned port
     * (instead of reaching for the commercial package) for the quota checks a future
     * upload-level limit would need.
     */
    public function __construct(
        private readonly EntitlementGate $entitlements,
    ) {}

    /**
     * Create an economic import and enqueue its document analysis.
     *
     * Uploading no longer meters anything: the analysis job reads the ticket offline
     * first, and only reserves a recognition credit when that free pass cannot find the
     * total. A plan without image recognition can therefore still upload a ticket.
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

        // The job reserves the credit itself, and only if the offline pass cannot read
        // the receipt, so no operation id is handed over here.
        AnalyzeEconomicDocumentJob::dispatch($import->id);

        return $import;
    }
}
