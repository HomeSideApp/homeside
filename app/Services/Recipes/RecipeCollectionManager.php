<?php

namespace App\Services\Recipes;

use App\Models\RecipeCollection;
use App\Models\User;
use Illuminate\Support\Str;

/**
 * Creates or reuses the chain of recipe collections matching a path.
 */
final class RecipeCollectionManager
{
    /**
     * @param  User  $owner  The owner of the collections
     * @param  string|null  $path  The slash separated collection path
     * @return ?RecipeCollection The ?RecipeCollection value.
     */
    public function findOrCreate(User $owner, ?string $path): ?RecipeCollection
    {
        $parts = collect(explode('/', trim($path ?? '', '/')))
            ->map(fn (string $part): string => trim($part))
            ->filter()
            ->values();

        if ($parts->isEmpty()) {
            return null;
        }

        $parent = null;
        $currentPath = '';

        foreach ($parts as $name) {
            $currentPath = ltrim($currentPath.'/'.$name, '/');
            $parent = RecipeCollection::firstOrCreate(
                ['owner_id' => $owner->id, 'path' => $currentPath],
                ['parent_id' => $parent?->id, 'name' => $name, 'slug' => Str::slug($name)],
            );
        }

        return $parent;
    }
}
