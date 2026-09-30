<?php

namespace App\Actions\Economy;

use App\Enums\TransactionScope;
use App\Enums\TransactionType;
use App\Models\EconomicTransaction;
use App\Models\Household;
use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * Computes household economy statistics, optionally restricted to a month.
 */
final class GetEconomyStats
{
    /**
     * Calculate household economy statistics from transactions visible to a user.
     *
     * @param  Household  $household  The household whose economy statistics are being calculated.
     * @param  User  $user  The authenticated user whose personal transaction visibility must be enforced.
     * @param  string|null  $period  The optional month used to restrict the calculation range.
     * @return array<string, mixed> The formatted balance, expense breakdowns, and optional period.
     */
    public function execute(Household $household, User $user, ?string $period = null): array
    {
        $query = EconomicTransaction::where('household_id', $household->id)
            ->where(function ($query) use ($user): void {
                $query->where('scope', TransactionScope::Shared)
                    ->orWhere('created_by', $user->id);
            });

        if ($period) {
            $start = Carbon::parse($period)->startOfMonth();
            $end = Carbon::parse($period)->endOfMonth();
            $query->whereBetween('occurred_at', [$start, $end]);
        }

        $transactions = $query->get();

        $totalExpenses = $transactions
            ->where('type', TransactionType::Expense)
            ->sum('amount_minor');

        $totalIncome = $transactions
            ->where('type', TransactionType::Income)
            ->sum('amount_minor');

        $personalExpenses = $transactions
            ->where('type', TransactionType::Expense)
            ->where('scope', TransactionScope::Personal)
            ->sum('amount_minor');

        $sharedExpenses = $transactions
            ->where('type', TransactionType::Expense)
            ->where('scope', TransactionScope::Shared)
            ->sum('amount_minor');

        $byMemberTotals = $transactions
            ->where('type', TransactionType::Expense)
            ->groupBy('created_by')
            ->map(fn ($txs): int => (int) $txs->sum('amount_minor'));
        $memberNames = User::query()->whereIn('id', $byMemberTotals->keys())->pluck('name', 'id');
        $byMember = $byMemberTotals->map(fn (int $amount, string $creatorId): array => [
            'creator_id' => $creatorId,
            'name' => $memberNames->get($creatorId),
            'total_minor' => $amount,
        ])->values()->all();

        $byPlace = $transactions
            ->where('type', TransactionType::Expense)
            ->whereNotNull('place')
            ->groupBy('place')
            ->map(fn ($txs): int => (int) $txs->sum('amount_minor'))
            ->sortDesc()
            ->map(fn (int $amount, string $place): array => ['place' => $place, 'total_minor' => $amount])
            ->values()->all();

        $byCurrency = $transactions->groupBy('currency')
            ->map(fn ($rows): int => (int) $rows->sum('amount_minor'))
            ->all();

        return [
            'period' => $period,
            'expenses_minor' => (int) $totalExpenses,
            'income_minor' => (int) $totalIncome,
            'balance_minor' => (int) ((int) $totalIncome - (int) $totalExpenses),
            'personal_expenses_minor' => (int) $personalExpenses,
            'shared_expenses_minor' => (int) $sharedExpenses,
            'by_currency' => $byCurrency,
            'by_member' => $byMember,
            'by_place' => $byPlace,
        ];
    }
}
