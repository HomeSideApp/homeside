<?php

namespace App\Actions\Economy;

use App\Models\EconomicAccount;
use Illuminate\Support\Facades\DB;

/**
 * Deletes a private economic account.
 */
final class DeleteEconomicAccount
{
    /**
     * Delete an account, detaching it from its historical transactions.
     *
     * The foreign key uses nullOnDelete, so the movement history survives the deletion while
     * losing the account reference.
     *
     * @param  EconomicAccount  $account  The account to delete.
     * @return void This action does not return a value.
     */
    public function execute(EconomicAccount $account): void
    {
        DB::transaction(function () use ($account): void {
            $account->transactions()->update(['account_id' => null]);
            $account->delete();
        });
    }
}
