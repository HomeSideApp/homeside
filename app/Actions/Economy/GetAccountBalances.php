<?php

namespace App\Actions\Economy;

use App\Enums\TransactionType;
use App\Models\EconomicAccount;
use App\Models\EconomicTransaction;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Computes the current balance of every account owned by a user.
 */
final class GetAccountBalances
{
    /**
     * Calculate the balances of a user's accounts with a single aggregated query.
     *
     * The balance is derived as `initial_balance_minor + income - expenses` over the
     * transactions that reference each account, so no denormalized balance column needs to be
     * kept in sync.
     *
     * @param  User  $user  The user whose accounts are resolved.
     * @param  Collection<int, EconomicAccount>|null  $accounts  The accounts to evaluate, or null to load the active ones.
     * @return array<string, int> The balances in minor units keyed by account identifier.
     */
    public function execute(User $user, ?Collection $accounts = null): array
    {
        $accounts ??= EconomicAccount::query()
            ->ownedBy($user->id)
            ->active()
            ->get();

        $balances = $accounts->mapWithKeys(
            fn (EconomicAccount $account): array => [$account->id => (int) $account->initial_balance_minor],
        )->all();

        if ($accounts->isEmpty()) {
            return $balances;
        }

        $movements = EconomicTransaction::query()
            ->whereIn('account_id', $accounts->pluck('id'))
            ->selectRaw('account_id, type, SUM(amount_minor) as total_minor')
            ->groupBy('account_id', 'type')
            ->get();

        foreach ($movements as $movement) {
            $accountId = $movement->account_id;
            $total = (int) $movement->total_minor;

            $balances[$accountId] = ($balances[$accountId] ?? 0)
                + ($movement->type === TransactionType::Income ? $total : -$total);
        }

        return $balances;
    }
}
