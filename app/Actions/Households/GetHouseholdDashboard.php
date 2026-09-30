<?php

namespace App\Actions\Households;

use App\Actions\ShoppingLists\ListShoppingLists;
use App\Models\Household;
use App\Models\ListItem;
use App\Models\User;

/**
 * Gathers the summary data shown on the household dashboard.
 */
final class GetHouseholdDashboard
{
    public function __construct(
        private readonly ListShoppingLists $listShoppingLists,
    ) {}

    /**
     * List counts cover every list the user can access: the household's lists plus their private
     * ones, so the "to buy" badge matches what the lists page shows.
     *
     * @param  Household  $household  The household model instance.
     * @param  User  $user  The authenticated user viewing the dashboard.
     * @return array{pending_items_count: int, active_lists_count: int, household_recipes_count: int, enabled_modules: array<int, string>}
     */
    public function execute(Household $household, User $user): array
    {
        $listIds = $household->lists()->pluck('id');

        $householdPendingItemsCount = $listIds->isEmpty()
            ? 0
            : ListItem::whereIn('list_id', $listIds)
                ->where('is_checked', false)
                ->count();

        // Cross-scope counts: household lists + the private lists of the viewer.
        $accessibleLists = $this->listShoppingLists->execute($user);
        $pendingItemsCount = $accessibleLists->sum('pending_items_count');
        $activeListsCount = $accessibleLists->count();

        $enabledModules = $household->modules()
            ->where('enabled', true)
            ->pluck('module')
            ->sort()
            ->values()
            ->all();

        return [
            // The KPI card keeps the household-only figure; the tile badge uses the cross-scope one.
            'household_pending_items_count' => $householdPendingItemsCount,
            'pending_items_count' => (int) $pendingItemsCount,
            'active_lists_count' => $activeListsCount,
            // Recipes shared with this household, used by the module access tiles.
            'household_recipes_count' => $household->recipes()->count(),
            'enabled_modules' => $enabledModules,
        ];
    }
}
