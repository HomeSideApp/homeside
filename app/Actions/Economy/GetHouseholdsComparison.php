<?php

namespace App\Actions\Economy;

use App\Enums\HouseholdModule as HouseholdModuleEnum;
use App\Enums\TransactionScope;
use App\Enums\TransactionType;
use App\Models\EconomicTransaction;
use App\Models\EconomicTransactionParticipant;
use App\Models\Household;
use App\Models\HouseholdMember;
use App\Models\HouseholdModule;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Compares the user's current-month movements across every household they belong to, plus their
 * private account, on the dashboard.
 *
 * Own spending and the user's share of shared expenses are reported separately: they answer
 * different questions ("what did I spend there" versus "what does that household cost me"), so
 * collapsing them into one figure would be misleading. Income and the resulting net balance are
 * reported per destination too, so the cross-scope "this month" KPI cards and the per-destination
 * breakdown always come from the exact same payload and reconcile by construction.
 *
 * Visibility rules: only households where the user is still a member and that keep the economy
 * module enabled contribute; the private account covers movements without a household. Only the
 * user's own incomes count, mirroring the expenses rule.
 * Date rule: the effective date (occurred_at, falling back to created_at) places each movement in
 * the month, so undated imported tickets are never dropped.
 */
final class GetHouseholdsComparison
{
    /**
     * Build the per-destination comparison table for the current month.
     *
     * @param  User  $user  The authenticated user whose spending is compared.
     * @param  string|null  $currency  The currency to summarize, or null to resolve it from the data.
     * @return array{
     *     currency: string|null,
     *     month: string,
     *     rows: list<array{scope: string, household_id: string|null, name: string|null, own_minor: int, shared_participation_minor: int, balance_minor: int, income_minor: int, net_minor: int}>,
     *     totals: array{own_minor: int, shared_participation_minor: int, balance_minor: int, income_minor: int, net_minor: int},
     * }
     */
    public function execute(User $user, ?string $currency = null): array
    {
        $monthStart = now()->startOfMonth();
        $monthEnd = now()->endOfMonth();
        $month = $monthEnd->copy()->format('Y-m');

        $memberships = HouseholdMember::query()
            ->where('user_id', $user->id)
            ->get(['id', 'household_id']);

        $activeHouseholdIds = $this->householdsWithEconomyEnabled($memberships->pluck('household_id'));

        $own = EconomicTransaction::query()
            ->ownedOrPrivateOf($user->id)
            ->ofType(TransactionType::Expense)
            ->where(function ($query) use ($activeHouseholdIds): void {
                $query->whereNull('household_id')
                    ->orWhereIn('household_id', $activeHouseholdIds);
            })
            ->effectiveOccurredBetween($monthStart, $monthEnd)
            ->get(['scope', 'amount_minor', 'currency', 'household_id']);

        $incomes = EconomicTransaction::query()
            ->ownedOrPrivateOf($user->id)
            ->ofType(TransactionType::Income)
            ->where(function ($query) use ($activeHouseholdIds): void {
                $query->whereNull('household_id')
                    ->orWhereIn('household_id', $activeHouseholdIds);
            })
            ->effectiveOccurredBetween($monthStart, $monthEnd)
            ->get(['amount_minor', 'currency', 'household_id']);

        $participations = EconomicTransactionParticipant::query()
            ->whereIn('household_member_id', $memberships->pluck('id'))
            ->whereHas('transaction', function ($query) use ($user, $activeHouseholdIds, $monthStart, $monthEnd): void {
                $query->where('created_by', '!=', $user->id)
                    ->ofType(TransactionType::Expense)
                    ->ofScope(TransactionScope::Shared)
                    ->whereIn('household_id', $activeHouseholdIds)
                    ->effectiveOccurredBetween($monthStart, $monthEnd);
            })
            ->with('transaction:id,currency,household_id')
            ->get(['transaction_id', 'amount_minor']);
        $resolvedCurrency = $currency ?? $this->resolveCurrency($own, $participations);

        $own = $this->filterCurrency($own, $resolvedCurrency);
        $participations = $this->filterParticipationCurrency($participations, $resolvedCurrency);
        $incomes = $this->filterCurrency($incomes, $resolvedCurrency);

        $ownTotal = (int) $own->sum('amount_minor');
        $sharedTotal = (int) $participations->sum('amount_minor');
        $incomeTotal = (int) $incomes->sum('amount_minor');

        return [
            'currency' => $resolvedCurrency,
            'month' => $month,
            'rows' => $this->rows($own, $participations, $incomes),
            'totals' => [
                'own_minor' => $ownTotal,
                'shared_participation_minor' => $sharedTotal,
                'balance_minor' => $ownTotal + $sharedTotal,
                'income_minor' => $incomeTotal,
                'net_minor' => $incomeTotal - ($ownTotal + $sharedTotal),
            ],
        ];
    }

    /**
     * Build one row per destination that has movement in the current month.
     *
     * @param  Collection<int, EconomicTransaction>  $own
     * @param  Collection<int, EconomicTransactionParticipant>  $participations
     * @param  Collection<int, EconomicTransaction>  $incomes
     * @return list<array{scope: string, household_id: string|null, name: string|null, own_minor: int, shared_participation_minor: int, balance_minor: int, income_minor: int, net_minor: int}>
     */
    private function rows(Collection $own, Collection $participations, Collection $incomes): array
    {
        $ownByDestination = $own
            ->groupBy(fn (EconomicTransaction $transaction): string => $transaction->household_id ?? 'private')
            ->map(fn (Collection $group): int => (int) $group->sum('amount_minor'));

        $sharedByDestination = $participations
            ->groupBy(fn (EconomicTransactionParticipant $participant): string => $participant->transaction->household_id ?? 'private')
            ->map(fn (Collection $group): int => (int) $group->sum('amount_minor'));

        $incomeByDestination = $incomes
            ->groupBy(fn (EconomicTransaction $transaction): string => $transaction->household_id ?? 'private')
            ->map(fn (Collection $group): int => (int) $group->sum('amount_minor'));

        $keys = $ownByDestination->keys()
            ->merge($sharedByDestination->keys())
            ->merge($incomeByDestination->keys())
            ->unique()
            ->values();

        if ($keys->isEmpty()) {
            return [];
        }

        $householdIds = $keys->reject(fn (string $key): bool => $key === 'private');
        $names = $householdIds->isNotEmpty()
            ? Household::query()->whereIn('id', $householdIds)->pluck('name', 'id')
            : new Collection;

        return $keys
            ->map(function (string $key) use ($ownByDestination, $sharedByDestination, $incomeByDestination, $names): array {
                $ownMinor = (int) ($ownByDestination[$key] ?? 0);
                $sharedMinor = (int) ($sharedByDestination[$key] ?? 0);
                $incomeMinor = (int) ($incomeByDestination[$key] ?? 0);

                return [
                    'scope' => $key === 'private' ? 'private' : 'household',
                    'household_id' => $key === 'private' ? null : $key,
                    'name' => $key === 'private' ? null : $names->get($key),
                    'own_minor' => $ownMinor,
                    'shared_participation_minor' => $sharedMinor,
                    'balance_minor' => $ownMinor + $sharedMinor,
                    'income_minor' => $incomeMinor,
                    'net_minor' => $incomeMinor - ($ownMinor + $sharedMinor),
                ];
            })
            ->sortByDesc('balance_minor')
            ->values()
            ->all();
    }

    /**
     * Resolve which of the given households keep the economy module enabled.
     *
     * @param  Collection<int, string>  $householdIds  The household identifiers to check.
     * @return list<string> The identifiers of the households where the economy module is enabled.
     */
    private function householdsWithEconomyEnabled(Collection $householdIds): array
    {
        if ($householdIds->isEmpty()) {
            return [];
        }

        return HouseholdModule::query()
            ->whereIn('household_id', $householdIds)
            ->where('module', HouseholdModuleEnum::Economy->value)
            ->where('enabled', true)
            ->pluck('household_id')
            ->all();
    }

    /**
     * Pick the currency with the largest own expense volume, falling back to shared participation.
     *
     * @param  Collection<int, EconomicTransaction>  $own
     * @param  Collection<int, EconomicTransactionParticipant>  $participations
     */
    private function resolveCurrency(Collection $own, Collection $participations): ?string
    {
        $ownByCurrency = $own->groupBy('currency')
            ->map(fn (Collection $group): int => (int) $group->sum('amount_minor'));

        if ($ownByCurrency->isNotEmpty()) {
            return $ownByCurrency->sortDesc()->keys()->first();
        }

        $sharedByCurrency = $participations
            ->groupBy(fn (EconomicTransactionParticipant $participant): string => $participant->transaction->currency)
            ->map(fn (Collection $group): int => (int) $group->sum('amount_minor'));

        return $sharedByCurrency->isNotEmpty()
            ? $sharedByCurrency->sortDesc()->keys()->first()
            : null;
    }

    /**
     * @param  Collection<int, EconomicTransaction>  $transactions
     * @return Collection<int, EconomicTransaction>
     */
    private function filterCurrency(Collection $transactions, ?string $currency): Collection
    {
        return $currency === null
            ? $transactions
            : $transactions->where('currency', $currency);
    }

    /**
     * @param  Collection<int, EconomicTransactionParticipant>  $participations
     * @return Collection<int, EconomicTransactionParticipant>
     */
    private function filterParticipationCurrency(Collection $participations, ?string $currency): Collection
    {
        return $currency === null
            ? $participations
            : $participations->filter(
                fn (EconomicTransactionParticipant $participant): bool => $participant->transaction->currency === $currency
            );
    }
}
