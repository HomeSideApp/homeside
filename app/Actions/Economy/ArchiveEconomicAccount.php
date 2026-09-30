<?php

namespace App\Actions\Economy;

use App\Models\EconomicAccount;

/**
 * Archives or restores a private economic account.
 */
final class ArchiveEconomicAccount
{
    /**
     * Toggle the archived state of an account.
     *
     * Archiving hides the account from the active lists without losing the movements that
     * reference it, which is preferable to deleting history.
     *
     * @param  EconomicAccount  $account  The account to archive or restore.
     * @param  bool  $archived  True to archive the account, false to restore it.
     * @return EconomicAccount The refreshed account.
     */
    public function execute(EconomicAccount $account, bool $archived = true): EconomicAccount
    {
        $account->update(['archived_at' => $archived ? now() : null]);

        return $account->refresh();
    }
}
