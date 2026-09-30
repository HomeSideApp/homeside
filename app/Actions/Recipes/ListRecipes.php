<?php

namespace App\Actions\Recipes;

use App\Models\Recipe;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Lists the recipes owned by a user, applying optional filters.
 */
final class ListRecipes
{
    /**
     * @param  User  $user  The owning user
     * @param  array{search?: string|null, tag?: string|null, difficulty?: string|null, cuisine?: string|null, collection?: string|null, perPage?: int, sort?: string, direction?: string}  $filters
     * @return LengthAwarePaginator<int, Recipe>
     */
    public function execute(User $user, array $filters = []): LengthAwarePaginator
    {
        $query = Recipe::query()
            ->where('owner_id', $user->id)
            ->with([
                'tags',
                'owner',
                'collection',
            ]);

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhere('author', 'like', "%{$search}%")
                    ->orWhere('cuisine', 'like', "%{$search}%");
            });
        }

        if (! empty($filters['tag'])) {
            $query->whereHas('tags', function ($q) use ($filters) {
                $q->where('name', $filters['tag']);
            });
        }

        if (! empty($filters['difficulty'])) {
            $query->where('difficulty', $filters['difficulty']);
        }

        if (! empty($filters['cuisine'])) {
            $query->where('cuisine', $filters['cuisine']);
        }

        if (! empty($filters['collection'])) {
            $query->where('collection_id', $filters['collection']);
        }

        return $query->orderBy($filters['sort'] ?? 'created_at', $filters['direction'] ?? 'desc')
            ->orderBy('id', $filters['direction'] ?? 'desc')
            ->paginate(perPage: $filters['perPage'] ?? 12, columns: ['*'], pageName: 'page');
    }
}
