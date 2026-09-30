<?php

namespace App\Actions\Economy;

use App\Enums\TransactionScope;
use App\Enums\TransactionType;
use App\Models\EconomicTransaction;
use App\Models\Household;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;

/**
 * Computes the household economy overview shown on the household dashboard tab:
 * current month totals, a 6-month evolution series, top places and spending by member,
 * all restricted to the currency with the largest expense volume.
 */
final class GetHouseholdEconomyOverview
{
    private const TOP_PLACES = 5;

    /**
     * Build the household economy overview for the dashboard.
     *
     * @param  Household  $household  The household whose economy is being summarized.
     * @param  User  $user  The authenticated user whose personal transaction visibility must be enforced.
     * @return array{
     *     currency: string|null,
     *     other_currencies: array<string, int>,
     *     current_month: string,
     *     totals: array{expenses_minor: int, income_minor: int, balance_minor: int, personal_expenses_minor: int, shared_expenses_minor: int},
     *     monthly: list<array{month: string, expenses_minor: int, income_minor: int}>,
     *     by_place: list<array{place: string, total_minor: int}>,
     *     by_member: list<array{creator_id: string, name: string, total_minor: int}>,
     * }
     */
    public function execute(Household $household, User $user, int $months = 12, ?string $requestedCurrency = null): array
    {
        $windowStart = now()->subMonths($months - 1)->startOfMonth();
        $windowEnd = now()->endOfMonth();
        $currentMonth = $windowEnd->copy()->format('Y-m');

        $transactions = EconomicTransaction::query()
            ->forHousehold($household->id)
            ->visibleToMember($user->id)
            ->effectiveOccurredBetween($windowStart, $windowEnd)
            ->get(['created_by', 'type', 'scope', 'amount_minor', 'currency', 'place', 'occurred_at', 'created_at']);

        $currency = $requestedCurrency ?? $this->resolveCurrency($transactions);

        return [
            'currency' => $currency,
            'other_currencies' => $this->otherCurrencies($transactions, $currentMonth, $currency),
            'current_month' => $currentMonth,
            'totals' => $this->totals($transactions, $currentMonth, $currency),
            'monthly' => $this->monthly($transactions, $windowStart, $currency, $months),
            'by_place' => $this->byPlace($transactions, $currentMonth, $currency),
            'by_member' => $this->byMember($transactions, $currentMonth, $currency),
        ];
    }

    /**
     * Pick the currency with the largest expense volume, falling back to income volume.
     *
     * @param  Collection<int, EconomicTransaction>  $transactions
     */
    private function resolveCurrency(Collection $transactions): ?string
    {
        $expenses = $transactions
            ->where('type', TransactionType::Expense)
            ->groupBy('currency')
            ->map(fn (Collection $group): int => (int) $group->sum('amount_minor'));

        if ($expenses->isNotEmpty()) {
            return $expenses->sortDesc()->keys()->first();
        }

        $incomes = $transactions
            ->where('type', TransactionType::Income)
            ->groupBy('currency')
            ->map(fn (Collection $group): int => (int) $group->sum('amount_minor'));

        return $incomes->isNotEmpty() ? $incomes->sortDesc()->keys()->first() : null;
    }

    /**
     * Summarize current month totals for the selected currency.
     *
     * @param  Collection<int, EconomicTransaction>  $transactions
     * @return array{expenses_minor: int, income_minor: int, balance_minor: int, personal_expenses_minor: int, shared_expenses_minor: int}
     */
    private function totals(Collection $transactions, string $currentMonth, ?string $currency): array
    {
        $current = $this->forMonth($transactions, $currentMonth, $currency);

        $expenseRows = $current->where('type', TransactionType::Expense);
        $totalExpenses = (int) $expenseRows->sum('amount_minor');
        $totalIncome = (int) $current->where('type', TransactionType::Income)->sum('amount_minor');

        return [
            'expenses_minor' => $totalExpenses,
            'income_minor' => $totalIncome,
            'balance_minor' => (int) ($totalIncome - $totalExpenses),
            'personal_expenses_minor' => (int) $expenseRows->where('scope', TransactionScope::Personal)->sum('amount_minor'),
            'shared_expenses_minor' => (int) $expenseRows->where('scope', TransactionScope::Shared)->sum('amount_minor'),
        ];
    }

    /**
     * Build the zero-filled monthly expense/income series for the selected currency.
     *
     * @param  Collection<int, EconomicTransaction>  $transactions
     * @return list<array{month: string, expenses_minor: int, income_minor: int}>
     */
    private function monthly(Collection $transactions, CarbonInterface $windowStart, ?string $currency, int $months): array
    {
        $grouped = $transactions
            ->when($currency !== null, fn (Collection $q) => $q->where('currency', $currency))
            ->groupBy(fn (EconomicTransaction $transaction): string => $transaction->effectiveOccurredAt()->format('Y-m'));

        $monthly = [];
        for ($i = 0; $i < $months; $i++) {
            $month = $windowStart->copy()->addMonths($i)->format('Y-m');
            $bucket = $grouped->get($month, new Collection);

            $monthly[] = [
                'month' => $month,
                'expenses_minor' => (int) $bucket->where('type', TransactionType::Expense)->sum('amount_minor'),
                'income_minor' => (int) $bucket->where('type', TransactionType::Income)->sum('amount_minor'),
            ];
        }

        return $monthly;
    }

    /**
     * Build the top expense places ranking for the current month.
     *
     * @param  Collection<int, EconomicTransaction>  $transactions
     * @return list<array{place: string, total_minor: int}>
     */
    private function byPlace(Collection $transactions, string $currentMonth, ?string $currency): array
    {
        return $this->forMonth($transactions, $currentMonth, $currency)
            ->where('type', TransactionType::Expense)
            ->whereNotNull('place')
            ->groupBy('place')
            ->map(fn (Collection $group): int => (int) $group->sum('amount_minor'))
            ->sortDesc()
            ->take(self::TOP_PLACES)
            ->map(fn (int $total, string $place): array => ['place' => $place, 'total_minor' => $total])
            ->values()
            ->all();
    }

    /**
     * Build the current month expense breakdown per transaction creator.
     *
     * @param  Collection<int, EconomicTransaction>  $transactions
     * @return list<array{creator_id: string, name: string, total_minor: int}>
     */
    private function byMember(Collection $transactions, string $currentMonth, ?string $currency): array
    {
        $byCreator = $this->forMonth($transactions, $currentMonth, $currency)
            ->where('type', TransactionType::Expense)
            ->groupBy('created_by')
            ->map(fn (Collection $group): int => (int) $group->sum('amount_minor'))
            ->sortDesc();

        if ($byCreator->isEmpty()) {
            return [];
        }

        $names = User::query()
            ->whereIn('id', $byCreator->keys())
            ->pluck('name', 'id');

        return $byCreator
            ->map(fn (int $total, string $creatorId): array => [
                'creator_id' => $creatorId,
                'name' => $names->get($creatorId, '—'),
                'total_minor' => $total,
            ])
            ->values()
            ->all();
    }

    /**
     * Report current month expense totals per currency other than the selected one.
     *
     * @param  Collection<int, EconomicTransaction>  $transactions
     * @return array<string, int>
     */
    private function otherCurrencies(Collection $transactions, string $currentMonth, ?string $currency): array
    {
        return $this->forMonth($transactions, $currentMonth, null)
            ->where('type', TransactionType::Expense)
            ->when($currency !== null, fn (Collection $q) => $q->where('currency', '!=', $currency))
            ->groupBy('currency')
            ->map(fn (Collection $group): int => (int) $group->sum('amount_minor'))
            ->sortDesc()
            ->all();
    }

    /**
     * Filter transactions to the given month and currency.
     *
     * @param  Collection<int, EconomicTransaction>  $transactions
     * @return Collection<int, EconomicTransaction>
     */
    private function forMonth(Collection $transactions, string $month, ?string $currency): Collection
    {
        return $transactions
            ->when($currency !== null, fn (Collection $q) => $q->where('currency', $currency))
            ->filter(fn (EconomicTransaction $transaction): bool => $transaction->effectiveOccurredAt()->format('Y-m') === $month);
    }
}
