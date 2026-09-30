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
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;

/**
 * Computes the cross-household personal economy overview shown on the personal
 * dashboard tab: current month totals, a 12-month evolution series and the
 * per-household expense breakdown, all restricted to the currency with the
 * largest personal expense volume.
 *
 * Date rule: a transaction is placed in time by `occurred_at`, falling back to `created_at`
 * when the ticket had no readable date. Without that fallback those transactions would be
 * invisible on the dashboard while still being listed on the personal economy page.
 *
 * Visibility rule: own expenses are limited to the households the user still belongs to (plus
 * their private, household-less expenses), so leaving a household also removes its data from
 * the personal overview. This matches the personal transaction list.
 */
final class GetPersonalEconomyOverview
{
    /**
     * Build the personal economy overview for the dashboard.
     *
     * @param  User  $user  The authenticated user whose cross-household personal economy is being summarized.
     * @param  int  $months  The number of months covered by the evolution series.
     * @param  string|null  $requestedCurrency  The currency to summarize, or null to resolve it from the data.
     * @return array{
     *     currency: string|null,
     *     other_currencies: array<string, int>,
     *     current_month: string,
     *     totals: array{own_minor: int, shared_participation_minor: int, personal_total_minor: int, without_house_minor: int},
     *     monthly: list<array{month: string, own_minor: int, shared_minor: int}>,
     *     by_household: list<array{household_id: string|null, name: string|null, total_minor: int}>,
     * }
     */
    public function execute(User $user, int $months = 12, ?string $requestedCurrency = null): array
    {
        $windowStart = now()->subMonths($months - 1)->startOfMonth();
        $windowEnd = now()->endOfMonth();
        $currentMonth = $windowEnd->copy()->format('Y-m');

        $memberships = HouseholdMember::query()
            ->where('user_id', $user->id)
            ->get(['id', 'household_id']);

        // Only households that still have the economy module enabled contribute to the personal
        // overview: a household that turned the module off must stop feeding these figures.
        $activeHouseholdIds = $this->householdsWithEconomyEnabled($memberships->pluck('household_id'));

        $own = EconomicTransaction::query()
            ->ownedOrPrivateOf($user->id)
            ->ofType(TransactionType::Expense)
            ->where(function ($query) use ($activeHouseholdIds): void {
                $query->whereNull('household_id')
                    ->orWhereIn('household_id', $activeHouseholdIds);
            })
            ->effectiveOccurredBetween($windowStart, $windowEnd)
            ->get(['household_id', 'amount_minor', 'currency', 'occurred_at', 'created_at']);

        $participations = EconomicTransactionParticipant::query()
            ->whereIn('household_member_id', $memberships->pluck('id'))
            ->whereHas('transaction', function ($query) use ($user, $activeHouseholdIds, $windowStart, $windowEnd): void {
                $query->where('created_by', '!=', $user->id)
                    ->ofType(TransactionType::Expense)
                    ->ofScope(TransactionScope::Shared)
                    ->whereIn('household_id', $activeHouseholdIds)
                    ->effectiveOccurredBetween($windowStart, $windowEnd);
            })
            ->with('transaction:id,currency,household_id,occurred_at,created_at')
            ->get(['transaction_id', 'household_member_id', 'amount_minor']);

        $currency = $requestedCurrency ?? $this->resolveCurrency($own, $participations);

        return [
            'currency' => $currency,
            'other_currencies' => $this->otherCurrencies($own, $participations, $currentMonth, $currency),
            'current_month' => $currentMonth,
            'totals' => $this->totals($own, $participations, $currentMonth, $currency),
            'monthly' => $this->monthly($own, $participations, $windowStart, $currency, $months),
            'by_household' => $this->byHousehold($own, $participations, $currentMonth, $currency),
        ];
    }

    /**
     * Resolve which of the user's households still have the economy module enabled.
     *
     * @param  \Illuminate\Support\Collection<int, string>  $householdIds  The household identifiers to check.
     * @return list<string> The identifiers of the households where the economy module is enabled.
     */
    private function householdsWithEconomyEnabled(\Illuminate\Support\Collection $householdIds): array
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
     * Own expenses take precedence so that a large participation in a second currency cannot
     * displace the currency the user actually spends in, mirroring the household overview.
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

        $participationsByCurrency = $participations
            ->groupBy(fn (EconomicTransactionParticipant $participant): string => $participant->transaction->currency)
            ->map(fn (Collection $group): int => (int) $group->sum('amount_minor'));

        return $participationsByCurrency->isNotEmpty()
            ? $participationsByCurrency->sortDesc()->keys()->first()
            : null;
    }

    /**
     * Summarize current month personal totals for the selected currency.
     *
     * @param  Collection<int, EconomicTransaction>  $own
     * @param  Collection<int, EconomicTransactionParticipant>  $participations
     * @return array{own_minor: int, shared_participation_minor: int, personal_total_minor: int, without_house_minor: int}
     */
    private function totals(Collection $own, Collection $participations, string $currentMonth, ?string $currency): array
    {
        $ownCurrent = $this->ownForMonth($own, $currentMonth, $currency);
        $ownTotal = (int) $ownCurrent->sum('amount_minor');
        $sharedTotal = (int) $this->participationsForMonth($participations, $currentMonth, $currency)->sum('amount_minor');

        return [
            'own_minor' => $ownTotal,
            'shared_participation_minor' => $sharedTotal,
            'personal_total_minor' => (int) ($ownTotal + $sharedTotal),
            'without_house_minor' => (int) $ownCurrent->whereNull('household_id')->sum('amount_minor'),
        ];
    }

    /**
     * Build the zero-filled monthly own/participation expense series.
     *
     * @param  Collection<int, EconomicTransaction>  $own
     * @param  Collection<int, EconomicTransactionParticipant>  $participations
     * @return list<array{month: string, own_minor: int, shared_minor: int}>
     */
    private function monthly(Collection $own, Collection $participations, CarbonInterface $windowStart, ?string $currency, int $months): array
    {
        $ownByMonth = $this->filterCurrency($own, $currency)
            ->groupBy(fn (EconomicTransaction $transaction): string => $transaction->effectiveOccurredAt()->format('Y-m'));

        $sharedByMonth = $this->filterParticipationCurrency($participations, $currency)
            ->groupBy(fn (EconomicTransactionParticipant $participant): string => $participant->transaction->effectiveOccurredAt()->format('Y-m'));

        $monthly = [];
        for ($i = 0; $i < $months; $i++) {
            $month = $windowStart->copy()->addMonths($i)->format('Y-m');

            $monthly[] = [
                'month' => $month,
                'own_minor' => (int) $ownByMonth->get($month, new Collection)->sum('amount_minor'),
                'shared_minor' => (int) $sharedByMonth->get($month, new Collection)->sum('amount_minor'),
            ];
        }

        return $monthly;
    }

    /**
     * Build the current month personal expense breakdown per household.
     *
     * @param  Collection<int, EconomicTransaction>  $own
     * @param  Collection<int, EconomicTransactionParticipant>  $participations
     * @return list<array{household_id: string|null, name: string|null, total_minor: int}>
     */
    private function byHousehold(Collection $own, Collection $participations, string $currentMonth, ?string $currency): array
    {
        $totals = new Collection;

        $this->ownForMonth($own, $currentMonth, $currency)
            ->groupBy(fn (EconomicTransaction $transaction): string => $transaction->household_id ?? 'none')
            ->each(function (Collection $group, string $key) use ($totals): void {
                $totals[$key] = ($totals[$key] ?? 0) + (int) $group->sum('amount_minor');
            });

        $this->participationsForMonth($participations, $currentMonth, $currency)
            ->groupBy(fn (EconomicTransactionParticipant $participant): string => $participant->transaction->household_id ?? 'none')
            ->each(function (Collection $group, string $key) use ($totals): void {
                $totals[$key] = ($totals[$key] ?? 0) + (int) $group->sum('amount_minor');
            });

        if ($totals->isEmpty()) {
            return [];
        }

        $householdIds = $totals->keys()->reject(fn (string $key): bool => $key === 'none');
        $names = $householdIds->isNotEmpty()
            ? Household::query()->whereIn('id', $householdIds)->pluck('name', 'id')
            : new Collection;

        return $totals
            ->sortDesc()
            ->map(fn (int $total, string $key): array => [
                'household_id' => $key === 'none' ? null : $key,
                'name' => $key === 'none' ? null : $names->get($key),
                'total_minor' => $total,
            ])
            ->values()
            ->all();
    }

    /**
     * Report current month personal totals per currency other than the selected one.
     *
     * @param  Collection<int, EconomicTransaction>  $own
     * @param  Collection<int, EconomicTransactionParticipant>  $participations
     * @return array<string, int>
     */
    private function otherCurrencies(Collection $own, Collection $participations, string $currentMonth, ?string $currency): array
    {
        $byCurrency = $this->ownForMonth($own, $currentMonth, null)
            ->where('currency', '!=', $currency)
            ->groupBy('currency')
            ->map(fn (Collection $group): int => (int) $group->sum('amount_minor'));

        $this->participationsForMonth($participations, $currentMonth, null)
            ->reject(fn (EconomicTransactionParticipant $participant): bool => $participant->transaction->currency === $currency)
            ->groupBy(fn (EconomicTransactionParticipant $participant): string => $participant->transaction->currency)
            ->each(function (Collection $group, string $other) use ($byCurrency): void {
                $byCurrency[$other] = ($byCurrency[$other] ?? 0) + (int) $group->sum('amount_minor');
            });

        return $byCurrency->sortDesc()->all();
    }

    /**
     * @param  Collection<int, EconomicTransaction>  $own
     * @return Collection<int, EconomicTransaction>
     */
    private function ownForMonth(Collection $own, string $month, ?string $currency): Collection
    {
        return $this->filterCurrency($own, $currency)
            ->filter(fn (EconomicTransaction $transaction): bool => $transaction->effectiveOccurredAt()->format('Y-m') === $month);
    }

    /**
     * @param  Collection<int, EconomicTransactionParticipant>  $participations
     * @return Collection<int, EconomicTransactionParticipant>
     */
    private function participationsForMonth(Collection $participations, string $month, ?string $currency): Collection
    {
        return $this->filterParticipationCurrency($participations, $currency)
            ->filter(fn (EconomicTransactionParticipant $participant): bool => $participant->transaction->effectiveOccurredAt()->format('Y-m') === $month);
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
            : $participations->filter(fn (EconomicTransactionParticipant $participant): bool => $participant->transaction->currency === $currency);
    }
}
