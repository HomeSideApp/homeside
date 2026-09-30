<?php

namespace App\Actions\Economy;

use App\Models\EconomicTransaction;

/**
 * Loads a transaction with its items, taxes, participants and document.
 */
final class GetEconomicTransaction
{
    /**
     * @param  EconomicTransaction  $transaction  The transaction to load
     * @return EconomicTransaction The EconomicTransaction value.
     */
    public function execute(EconomicTransaction $transaction): EconomicTransaction
    {
        $transaction->load([
            'items',
            'taxes',
            'participants.householdMember.user',
            'sourceDocument',
            'creator',
            'account.paymentMethods',
            'attachments.uploader',
            'contacts',
        ]);

        // Child resources format their amounts with the parent account precision, so the back
        // reference is set explicitly instead of relying on a lazy query per child row.
        foreach (['items', 'taxes', 'participants'] as $relation) {
            foreach ($transaction->getRelation($relation) as $child) {
                $child->setRelation('transaction', $transaction);
            }
        }

        return $transaction;
    }
}
