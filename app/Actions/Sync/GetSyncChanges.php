<?php

namespace App\Actions\Sync;

use App\Http\Resources\Economy\EconomicTransactionResource;
use App\Http\Resources\Households\HouseholdResource;
use App\Http\Resources\ShoppingLists\ListItemResource;
use App\Http\Resources\ShoppingLists\ShoppingListResource;
use App\Models\EconomicTransaction;
use App\Models\Household;
use App\Models\ListItem;
use App\Models\ShoppingList;
use App\Models\User;
use App\Support\HouseholdAccess;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * Builds one page of the offline delta feed.
 *
 * Every stream is queried with the same `(updated_at, id)` keyset predicate and
 * ordered the same way, so no single UNION across four tables is needed. The
 * per-stream pages are merged in PHP, sorted by the same key and truncated to
 * the requested limit; the cursor is the key of the last emitted row. Tombstones
 * are separate streams (deleted rows only), which keeps them out of `changes`.
 *
 * Scope mirrors what the caller may read today: personal lists plus the lists of
 * their households, the items of those lists, personal economy plus what the
 * household economy shows them, and the households they belong to.
 */
final class GetSyncChanges
{
    public const int DEFAULT_LIMIT = 500;

    public const int MAX_LIMIT = 500;

    /**
     * Build the delta page for the given caller.
     *
     * @param  Request  $request  The incoming request, used to serialize the resources.
     * @param  User  $user  The authenticated caller whose visibility is enforced.
     * @param  string|null  $since  The opaque cursor to continue from, or null for the initial state.
     * @param  int  $limit  The maximum number of rows to emit.
     * @return array{cursor: string, has_more: bool, changes: array<string, array<int, mixed>>, deleted: array<string, array<int, string>>} The delta page.
     *
     * @throws ValidationException When the cursor cannot be decoded.
     */
    public function execute(Request $request, User $user, ?string $since, int $limit): array
    {
        $cursor = $since === null ? null : SyncCursor::decode($since);

        if ($since !== null && $cursor === null) {
            throw ValidationException::withMessages(['since' => 'The given sync cursor is not valid.']);
        }

        $limit = min(max($limit, 1), self::MAX_LIMIT);
        $fetchLimit = $limit + 1;

        $householdAccess = app(HouseholdAccess::class);
        $userId = $user->id;

        $householdIds = $this->visibleHouseholdIds($user);
        $listHouseholdIds = $householdAccess->householdIds($user, 'shopping_lists');
        $economyHouseholdIds = $householdAccess->householdIds($user, 'economy');
        $economyMembershipIds = $householdAccess->memberships($user)
            ->whereIn('household_id', $economyHouseholdIds)
            ->pluck('id')
            ->all();

        $streams = [
            'changes.households' => $this->fetch($this->householdsQuery($householdIds), $cursor, $fetchLimit),
            'changes.lists' => $this->fetch($this->listsQuery($userId, $listHouseholdIds), $cursor, $fetchLimit),
            'changes.list_items' => $this->fetch($this->listItemsQuery($userId, $listHouseholdIds, false), $cursor, $fetchLimit),
            'changes.economy_transactions' => $this->fetch(
                $this->transactionsQuery($userId, $economyHouseholdIds, $economyMembershipIds),
                $cursor,
                $fetchLimit,
            ),
            'deleted.lists' => $this->fetch($this->listsQuery($userId, $listHouseholdIds)->onlyTrashed(), $cursor, $fetchLimit),
            'deleted.list_items' => $this->fetch($this->listItemsQuery($userId, $listHouseholdIds, true)->onlyTrashed(), $cursor, $fetchLimit),
            'deleted.economy_transactions' => $this->fetch(
                $this->transactionsQuery($userId, $economyHouseholdIds, $economyMembershipIds)->onlyTrashed(),
                $cursor,
                $fetchLimit,
            ),
        ];

        $entries = $this->entries($streams);
        $hasMore = count($entries) > $limit;
        $emitted = array_slice($entries, 0, $limit);
        $grouped = $this->groupByBucket($emitted);
        $last = $emitted === [] ? null : $emitted[array_key_last($emitted)];

        return [
            'cursor' => $last === null
                ? ($since ?? SyncCursor::initial())
                : SyncCursor::encode($last['updated_at']->format('Y-m-d H:i:s'), $last['id']),
            'has_more' => $hasMore,
            'changes' => [
                'households' => HouseholdResource::collection($grouped['changes.households'] ?? [])->resolve($request),
                'lists' => ShoppingListResource::collection($grouped['changes.lists'] ?? [])->resolve($request),
                'list_items' => ListItemResource::collection($grouped['changes.list_items'] ?? [])->resolve($request),
                'economy_transactions' => EconomicTransactionResource::collection($grouped['changes.economy_transactions'] ?? [])->resolve($request),
            ],
            'deleted' => [
                'lists' => $this->ids($grouped['deleted.lists'] ?? []),
                'list_items' => $this->ids($grouped['deleted.list_items'] ?? []),
                'economy_transactions' => $this->ids($grouped['deleted.economy_transactions'] ?? []),
            ],
        ];
    }

    /**
     * The households the caller currently belongs to.
     *
     * A caller without household features enabled has no visible household data.
     *
     * @return array<int, string> The visible household identifiers.
     */
    private function visibleHouseholdIds(User $user): array
    {
        if (! $user->households_enabled) {
            return [];
        }

        return $user->householdMemberships()->pluck('household_id')->all();
    }

    /**
     * @param  array<int, string>  $householdIds
     * @return Builder<Household>
     */
    private function householdsQuery(array $householdIds): Builder
    {
        return Household::query()->whereIn('id', $householdIds);
    }

    /**
     * @return Builder<ShoppingList>
     */
    private function listsQuery(string $userId, array $householdIds): Builder
    {
        $query = ShoppingList::query()->with('household:id,name');
        $this->applyListVisibility($query, $userId, $householdIds);

        return $query;
    }

    /**
     * @return Builder<ListItem>
     */
    private function listItemsQuery(string $userId, array $householdIds, bool $withTrashedLists): Builder
    {
        // Items of a tombstoned list are tombstoned too, so the deleted stream
        // must resolve the parent list without the soft-delete scope; otherwise
        // a cascaded deletion would never be reported.
        $listIds = ShoppingList::query()->select('id');

        if ($withTrashedLists) {
            $listIds->withTrashed();
        }

        $this->applyListVisibility($listIds, $userId, $householdIds);

        return ListItem::query()->whereIn('list_id', $listIds);
    }

    /**
     * @return Builder<EconomicTransaction>
     */
    private function transactionsQuery(string $userId, array $economyHouseholdIds, array $membershipIds): Builder
    {
        // Same visibility rule as the personal economy listing: the caller's own
        // transactions inside the households they still belong to, plus the
        // shared transactions they take part in.
        return EconomicTransaction::query()
            ->with('creator')
            ->where(function (Builder $query) use ($userId, $economyHouseholdIds, $membershipIds): void {
                $query->where(function (Builder $owned) use ($userId, $economyHouseholdIds): void {
                    $owned->where('created_by', $userId)
                        ->where(function (Builder $scope) use ($economyHouseholdIds): void {
                            $scope->whereNull('household_id')->orWhereIn('household_id', $economyHouseholdIds);
                        });
                })->orWhereHas(
                    'participants',
                    fn (Builder $participants) => $participants->whereIn('household_member_id', $membershipIds),
                );
            });
    }

    /**
     * Restrict a list query to personal lists of the caller plus household lists.
     *
     * @param  Builder<*>  $query
     * @param  array<int, string>  $householdIds
     */
    private function applyListVisibility(Builder $query, string $userId, array $householdIds): void
    {
        $query->where(function (Builder $nested) use ($userId, $householdIds): void {
            $nested->where(function (Builder $personal) use ($userId): void {
                $personal->whereNull('household_id')->where('created_by', $userId);
            })->orWhereIn('household_id', $householdIds);
        });
    }

    /**
     * Fetch one stream page: keyset predicate, ascending order, one row extra.
     *
     * The extra row is what proves whether more rows exist beyond this page; it
     * is never emitted.
     *
     * @param  Builder<*>  $query
     * @param  array{t: string, i: string}|null  $cursor
     * @return Collection<int, Model>
     */
    private function fetch(Builder $query, ?array $cursor, int $limit): Collection
    {
        if ($cursor !== null) {
            $query->where(function (Builder $nested) use ($cursor): void {
                $nested->where('updated_at', '>', $cursor['t'])
                    ->orWhere(function (Builder $sameTimestamp) use ($cursor): void {
                        $sameTimestamp->where('updated_at', '=', $cursor['t'])
                            ->where('id', '>', $cursor['i']);
                    });
            });
        }

        return $query->orderBy('updated_at')->orderBy('id')->limit($limit)->get();
    }

    /**
     * Flatten the stream pages into a single list ordered by `(updated_at, id)`.
     *
     * @param  array<string, Collection<int, Model>>  $streams
     * @return array<int, array{updated_at: Carbon, id: string, bucket: string, model: Model}>
     */
    private function entries(array $streams): array
    {
        $entries = [];

        foreach ($streams as $bucket => $models) {
            foreach ($models as $model) {
                /** @var Carbon|null $updatedAt */
                $updatedAt = $model->getAttribute('updated_at');

                $entries[] = [
                    'updated_at' => $updatedAt,
                    'id' => (string) $model->getKey(),
                    'bucket' => $bucket,
                    'model' => $model,
                ];
            }
        }

        usort($entries, static fn (array $left, array $right): int => [
            $left['updated_at']->format('Y-m-d H:i:s'),
            $left['id'],
        ] <=> [
            $right['updated_at']->format('Y-m-d H:i:s'),
            $right['id'],
        ]);

        return $entries;
    }

    /**
     * @param  array<int, array{updated_at: Carbon, id: string, bucket: string, model: Model}>  $entries
     * @return array<string, array<int, Model>>
     */
    private function groupByBucket(array $entries): array
    {
        $grouped = [];

        foreach ($entries as $entry) {
            $grouped[$entry['bucket']][] = $entry['model'];
        }

        return $grouped;
    }

    /**
     * @param  array<int, Model>  $models
     * @return array<int, string>
     */
    private function ids(array $models): array
    {
        return array_values(array_map(
            static fn (Model $model): string => (string) $model->getKey(),
            $models,
        ));
    }
}
