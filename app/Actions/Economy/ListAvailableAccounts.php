<?php

namespace App\Actions\Economy;

use App\Http\Resources\Economy\EconomicAccountResource;
use App\Models\EconomicAccount;
use App\Models\User;
use Illuminate\Http\Request;

/**
 * Lists the accounts a user may assign to an economic transaction.
 *
 * Only the owner sees their accounts, which keeps the funding source private even when the
 * transaction itself is shared with the household.
 */
final class ListAvailableAccounts
{
    /**
     * List the active accounts owned by the user, ordered by name.
     *
     * @param  User  $user  The user whose accounts are resolved.
     * @param  Request  $request  The request used to resolve each serialized account.
     * @return array<int, array<string, mixed>> The serialized active accounts available to the user.
     */
    public function execute(User $user, Request $request): array
    {
        return EconomicAccount::query()
            ->ownedBy($user->id)
            ->active()
            ->with('paymentMethods')
            ->orderBy('name')
            ->get()
            ->map(fn (EconomicAccount $account): array => (new EconomicAccountResource($account))->resolve($request))
            ->all();
    }
}
