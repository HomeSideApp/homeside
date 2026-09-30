<?php

namespace App\Actions\Economy;

use App\Enums\TransactionType;
use App\Models\EconomicTransaction;
use App\Models\EconomicTransactionParticipant;
use App\Models\Household;
use App\Models\HouseholdMember;
use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * Computes a user's personal expenses, including their own expenses and their
 * participation in shared expenses they did not create.
 *
 * This action has no default time window: without a period it aggregates every transaction the
 * user owns. When a period is given, transactions are placed in time by `occurred_at` with a
 * fallback to `created_at`, so tickets without a readable date are never silently dropped.
 */
final class GetPersonalTotalsStats
{
    /**
     * Calculate a user's owned expenses and shared expense participations across all households.
     *
     * @param  User  $user  The user whose personal economy totals are being calculated.
     * @param  string|null  $period  The optional month used to restrict the calculation range.
     * @return array<string, mixed> The expense totals in minor units, including household and currency breakdowns.
     */
    public function execute(User $user, ?string $period = null): array
    {
        [$start, $end] = $this->resolveRange($period);
        $memberships = HouseholdMember::where('user_id', $user->id)->get(['id', 'household_id']);
        $membershipIds = $memberships->pluck('id');
        $householdIds = $memberships->pluck('household_id');

        // Own expenses cover the private ones plus those in the households the user still belongs
        // to, matching the personal transaction list visibility.
        $own = EconomicTransaction::query()
            ->where('created_by', $user->id)
            ->where('type', TransactionType::Expense)
            ->where(function ($query) use ($householdIds): void {
                $query->whereNull('household_id')
                    ->orWhereIn('household_id', $householdIds);
            })
            ->when($start !== null, fn ($q) => $q->effectiveOccurredBetween($start, $end))
            ->get(['type', 'scope', 'amount_minor', 'currency', 'household_id']);

        $sharedParticipations = EconomicTransactionParticipant::query()
            ->whereIn('household_member_id', $membershipIds)
            ->whereHas('transaction', function ($q) use ($user, $start, $end) {
                $q->where('created_by', '!=', $user->id)
                    ->where('type', TransactionType::Expense)
                    ->where('scope', 'shared')
                    ->when($start !== null, fn ($qq) => $qq->effectiveOccurredBetween($start, $end));
            })
            ->with('transaction:id,currency,household_id')
            ->get(['transaction_id', 'amount_minor']);

        $ownTotal = (int) $own->sum('amount_minor');
        $sharedParts = (int) $sharedParticipations->sum('amount_minor');
        $byCurrency = $own->groupBy('currency')
            ->map(fn ($transactions) => (int) $transactions->sum('amount_minor'));

        $sharedParticipations
            ->groupBy(fn (EconomicTransactionParticipant $participant): string => $participant->transaction->currency)
            ->each(function ($participants, string $currency) use ($byCurrency): void {
                $byCurrency[$currency] = ($byCurrency[$currency] ?? 0) + (int) $participants->sum('amount_minor');
            });

        $byHouseholdTotals = $own->whereNotNull('household_id')
            ->groupBy('household_id')
            ->map(fn ($group): int => (int) $group->sum('amount_minor'));
        $sharedParticipations
            ->groupBy(fn (EconomicTransactionParticipant $participant): string => $participant->transaction->household_id)
            ->each(function ($participants, string $householdId) use ($byHouseholdTotals): void {
                $byHouseholdTotals[$householdId] = ($byHouseholdTotals[$householdId] ?? 0)
                    + (int) $participants->sum('amount_minor');
            });
        $householdNames = Household::query()->whereIn('id', $byHouseholdTotals->keys())->pluck('name', 'id');

        return [
            'period' => $period,
            'own_total_minor' => $ownTotal,
            'shared_participation_minor' => $sharedParts,
            'personal_total_minor' => (int) ($ownTotal + $sharedParts),
            'without_house_minor' => (int) $own->whereNull('household_id')->sum('amount_minor'),
            'by_household' => $byHouseholdTotals->map(fn (int $total, string $householdId): array => [
                'household_id' => $householdId,
                'name' => $householdNames->get($householdId),
                'total_minor' => $total,
            ])->values()->all(),
            'by_currency' => $byCurrency->toArray(),
        ];
    }

    /**
     * Resolve the inclusive monthly date range when a period is requested.
     *
     * @param  string|null  $period  The optional date whose month should be selected.
     * @return array{0: Carbon|null, 1: Carbon|null} The selected month's boundaries, or null boundaries when no period is requested.
     */
    private function resolveRange(?string $period): array
    {
        if (! $period) {
            return [null, null];
        }

        return [Carbon::parse($period)->startOfMonth(), Carbon::parse($period)->endOfMonth()];
    }
}
