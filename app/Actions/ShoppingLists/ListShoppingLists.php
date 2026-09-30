<?php

namespace App\Actions\ShoppingLists;

use App\Models\ShoppingList;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * Lists the shopping lists accessible to a user.
 */
final class ListShoppingLists
{
    /**
     * @param  array{search?: string|null, scope?: string|null, household?: string|null}  $filters
     * @return Collection<int, ShoppingList>
     */
    public function execute(User $user, array $filters = []): Collection
    {
        $query = ShoppingList::query()
            ->with([
                'creator:id,name',
                'household:id,name,color,image_url',
            ])
            ->withCount([
                'items',
                'items as pending_items_count' => fn (Builder $itemsQuery): Builder => $itemsQuery
                    ->where('is_checked', false),
            ]);

        $query->where(function (Builder $accessibleQuery) use ($user): void {
            $accessibleQuery->where(function (Builder $personalQuery) use ($user): void {
                $personalQuery
                    ->whereNull('household_id')
                    ->where('created_by', $user->id);
            });

            if ($user->households_enabled) {
                $accessibleQuery->orWhere(function (Builder $householdQuery) use ($user): void {
                    $householdQuery
                        ->whereNotNull('household_id')
                        ->whereHas('household.members', fn (Builder $membersQuery): Builder => $membersQuery
                            ->where('user_id', $user->id))
                        ->whereHas('household.modules', fn (Builder $modulesQuery): Builder => $modulesQuery
                            ->where('module', 'shopping_lists')
                            ->where('enabled', true));
                });
            }
        });

        $scope = $filters['scope'] ?? null;

        if ($scope === 'personal') {
            $query->whereNull('household_id');
        } elseif ($scope === 'household') {
            $query->whereNotNull('household_id');
        }

        if ($householdId = $filters['household'] ?? null) {
            $query->where('household_id', $householdId);
        }

        if ($search = $filters['search'] ?? null) {
            $query->where('name', 'like', "%{$search}%");
        }

        return $query
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get();
    }
}
