<?php

namespace App\Actions\Recipes;

use App\Models\Household;
use App\Models\HouseholdRecipe;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Lists the recipes shared with a household, with optional search.
 */
final class ListHouseholdRecipes
{
    /**
     * @param  Household  $household  The household to list recipes for
     * @param  array{search?: string|null, tag?: string|null, perPage?: int, sort?: string, direction?: string}  $filters
     * @return LengthAwarePaginator<int, HouseholdRecipe>
     */
    public function execute(Household $household, array $filters = []): LengthAwarePaginator
    {
        $query = HouseholdRecipe::where('household_id', $household->id)
            ->with([
                'recipe.tags',
                'recipe.ingredients.product',
                'recipe.owner',
                'sharedBy',
            ]);

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->whereHas('recipe', function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        if (! empty($filters['tag'])) {
            $query->whereHas('recipe.tags', fn ($tagQuery) => $tagQuery->where('name', $filters['tag']));
        }

        return $query->orderBy($filters['sort'] ?? 'created_at', $filters['direction'] ?? 'desc')
            ->orderBy('id', $filters['direction'] ?? 'desc')
            ->paginate(perPage: $filters['perPage'] ?? 12, columns: ['*'], pageName: 'page');
    }
}
