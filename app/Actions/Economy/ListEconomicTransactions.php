<?php

namespace App\Actions\Economy;

use App\Models\EconomicTransaction;
use App\Models\Household;
use App\Models\HouseholdMember;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Lists economic transactions for both the household scope and the cross-household personal scope.
 *
 * Date filters and ordering use the effective date (`occurred_at`, falling back to `created_at`) so
 * transactions without a readable ticket date are never hidden by a date filter.
 */
final class ListEconomicTransactions
{
    /**
     * List visible household transactions using the supplied filters.
     *
     * @param  Household  $household  The household whose transactions are being listed.
     * @param  User  $user  The authenticated user whose personal visibility must be enforced.
     * @param  array<string, mixed>  $filters  The optional type, scope, creator, date, search, and pagination filters.
     * @return LengthAwarePaginator<int, EconomicTransaction> The paginated visible household transactions.
     */
    public function execute(Household $household, User $user, array $filters = []): LengthAwarePaginator
    {
        $query = EconomicTransaction::where('household_id', $household->id)
            ->with(['creator']);

        if (isset($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        if (isset($filters['scope'])) {
            $query->where('scope', $filters['scope']);
        }

        if (isset($filters['created_by'])) {
            $query->where('created_by', $filters['created_by']);
        }

        // Date filters use the effective date, so a transaction whose `occurred_at` is unknown is
        // still reachable through the date it is displayed with.
        if (isset($filters['from'])) {
            $query->effectiveOccurredFrom($filters['from']);
        }
        if (isset($filters['to'])) {
            $query->effectiveOccurredUntil($filters['to']);
        }

        if (isset($filters['search'])) {
            $query->where(function ($q) use ($filters) {
                $q->where('title', 'like', "%{$filters['search']}%")
                    ->orWhere('place', 'like', "%{$filters['search']}%");
            });
        }

        $query->visibleToMember($user->id);

        $direction = $filters['direction'] ?? 'desc';
        $sortColumn = ($filters['sort'] ?? null) === 'occurred_at'
            ? 'COALESCE(occurred_at, created_at)'
            : ($filters['sort'] ?? 'occurred_at');

        return $query->orderByRaw($sortColumn.' '.$direction)
            ->orderBy('id', $direction)
            ->paginate(min(max((int) ($filters['perPage'] ?? 15), 1), 100))
            ->withQueryString();
    }

    /**
     * List transactions owned by or shared with a user across every economy scope.
     *
     * @param  User  $user  The user whose cross-household economy is being listed.
     * @param  array<string, mixed>  $filters  The optional type, date, search, and pagination filters.
     * @return LengthAwarePaginator<int, EconomicTransaction> The paginated personal economy transactions with household origins loaded.
     */
    public function executePersonal(User $user, array $filters = []): LengthAwarePaginator
    {
        $memberships = HouseholdMember::where('user_id', $user->id)->get(['id', 'household_id']);
        $membershipIds = $memberships->pluck('id');
        $householdIds = $memberships->pluck('household_id');

        // Visibility is intentionally limited to the households the user still belongs to: their
        // own transactions there or outside any household, plus the shared ones they take part in.
        // Data from a household the user has left must not resurface through this list.
        $query = EconomicTransaction::query()
            ->with(['creator', 'household'])
            ->where(function ($query) use ($user, $membershipIds, $householdIds): void {
                $query->where(function ($ownedQuery) use ($user, $householdIds): void {
                    $ownedQuery->where('created_by', $user->id)
                        ->where(function ($scopeQuery) use ($householdIds): void {
                            $scopeQuery->whereNull('household_id')
                                ->orWhereIn('household_id', $householdIds);
                        });
                })
                    ->orWhereHas('participants', fn ($pq) => $pq->whereIn('household_member_id', $membershipIds));
            });

        if (isset($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        if (isset($filters['account_id'])) {
            $query->where('account_id', $filters['account_id']);
        }

        // Filters use the effective date so that a transaction without `occurred_at` is still
        // found by the date the user actually sees on the list.
        if (isset($filters['from'])) {
            $query->effectiveOccurredFrom($filters['from']);
        }
        if (isset($filters['to'])) {
            $query->effectiveOccurredUntil($filters['to']);
        }

        if (isset($filters['search'])) {
            $query->where(function ($q) use ($filters) {
                $q->where('title', 'like', "%{$filters['search']}%")
                    ->orWhere('place', 'like', "%{$filters['search']}%");
            });
        }

        $direction = $filters['direction'] ?? 'desc';
        $sortColumn = ($filters['sort'] ?? null) === 'occurred_at'
            ? 'COALESCE(occurred_at, created_at)'
            : ($filters['sort'] ?? 'occurred_at');

        return $query->orderByRaw($sortColumn.' '.$direction)
            ->orderBy('id', $direction)
            ->paginate(min(max((int) ($filters['perPage'] ?? 15), 1), 100))
            ->withQueryString();
    }
}
