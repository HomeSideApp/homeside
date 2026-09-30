<?php

namespace App\Actions\Economy;

use App\Data\Economy\ImportFiltersData;
use App\Enums\EconomicImportStatus;
use App\Models\EconomicImport;
use App\Models\Household;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

/**
 * Lists the economic imports visible to a user so an analysis never becomes unreachable.
 */
final class ListEconomicImports
{
    /**
     * List the imports visible in the private scope of a user.
     *
     * @param  User  $user  The user whose private imports are listed.
     * @param  ImportFiltersData  $filters  The status, search and pagination filters.
     * @return LengthAwarePaginator<int, EconomicImport> The paginated private imports.
     */
    public function executePersonal(User $user, ImportFiltersData $filters): LengthAwarePaginator
    {
        return $this->applyFilters(
            EconomicImport::query()
                ->whereNull('household_id')
                ->where('created_by', $user->id),
            $filters,
        )->paginate($filters->per_page)->withQueryString();
    }

    /**
     * List the imports visible inside a household.
     *
     * Household imports are always visible to the whole household; the private imports a member
     * created outside the household never appear here.
     *
     * @param  Household  $household  The household whose imports are listed.
     * @param  User  $user  The authenticated member requesting the list.
     * @param  ImportFiltersData  $filters  The status, creator, search and pagination filters.
     * @return LengthAwarePaginator<int, EconomicImport> The paginated household imports.
     */
    public function executeHousehold(Household $household, User $user, ImportFiltersData $filters): LengthAwarePaginator
    {
        $query = EconomicImport::query()->where('household_id', $household->id);

        if ($filters->created_by !== null) {
            $query->where('created_by', $filters->created_by);
        }

        return $this->applyFilters($query->with('creator'), $filters)
            ->paginate($filters->per_page)
            ->withQueryString();
    }

    /**
     * Count the imports still in progress for a user and household scope.
     *
     * @param  User  $user  The user whose in-progress imports are counted.
     * @param  Household|null  $household  The household scope, or null for the private scope.
     * @return int The number of imports waiting for the AI analysis or the user review.
     */
    public function countPending(User $user, ?Household $household = null): int
    {
        return $this->countByStatuses($user, [
            EconomicImportStatus::Pending,
            EconomicImportStatus::Processing,
            EconomicImportStatus::ReadyForReview,
        ], $household);
    }

    /**
     * Count the imports of a user and scope that are in any of the given statuses.
     *
     * @param  User  $user  The user whose imports are counted.
     * @param  list<EconomicImportStatus>  $statuses  The statuses that must be counted.
     * @param  Household|null  $household  The household scope, or null for the private scope.
     * @return int The number of matching imports.
     */
    public function countByStatuses(User $user, array $statuses, ?Household $household = null): int
    {
        return EconomicImport::query()
            ->when(
                $household === null,
                fn ($query) => $query
                    ->whereNull('household_id')
                    ->where('created_by', $user->id),
                fn ($query) => $query->where('household_id', $household->id),
            )
            ->whereIn('status', array_map(
                static fn (EconomicImportStatus $status): string => $status->value,
                $statuses,
            ))
            ->count();
    }

    /**
     * Apply the shared filters and eager loading to an import query.
     *
     * @param  Builder<EconomicImport>  $query  The base import query.
     * @param  ImportFiltersData  $filters  The status and search filters to apply.
     * @return Builder<EconomicImport> The filtered query.
     */
    private function applyFilters(Builder $query, ImportFiltersData $filters): Builder
    {
        $statuses = $filters->statuses();

        if ($statuses !== []) {
            $query->whereIn('status', $statuses);
        }

        if ($filters->search !== null) {
            $query->whereHas(
                'document',
                fn ($documentQuery) => $documentQuery
                    ->where('original_filename', 'like', '%'.$filters->search.'%'),
            );
        }

        return $query->with(['document', 'creator'])->latest('created_at')->latest('id');
    }
}
