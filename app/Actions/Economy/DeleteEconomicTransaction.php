<?php

namespace App\Actions\Economy;

use App\Models\EconomicTransaction;

/**
 * Deletes an economic transaction and its related records.
 */
final class DeleteEconomicTransaction
{
    /**
     * @param  EconomicTransaction  $transaction  The transaction to delete
     */
    public function execute(EconomicTransaction $transaction): void
    {
        $transaction->delete();
    }
}
