<?php

namespace App\Actions\Economy;

use App\Enums\EconomicImportStatus;
use App\Models\EconomicImport;

/**
 * Marks an economic import as discarded.
 */
final class DiscardEconomicImport
{
    /**
     * @param  EconomicImport  $import  The import to discard
     */
    public function execute(EconomicImport $import): void
    {
        $import->update(['status' => EconomicImportStatus::Discarded]);
    }
}
