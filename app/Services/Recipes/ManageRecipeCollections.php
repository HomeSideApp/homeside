<?php

namespace App\Services\Recipes;

use App\Jobs\ReconcileRecipeReferences;
use App\Models\RecipeCollection;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Class ManageRecipeCollections
 *
 * This service manages the hierarchical recipe collections of a user, handling
 * creation, renaming, moving, deleting and path availability checks.
 */
final class ManageRecipeCollections
{
    public function create(User $owner, string $name, ?string $parentId): RecipeCollection
    {
        $parent = $parentId !== null
            ? RecipeCollection::query()->where('owner_id', $owner->id)->whereKey($parentId)->firstOrFail()
            : null;
        $path = $parent !== null ? $parent->path.'/'.$name : $name;

        $this->ensurePathIsAvailable($owner, $path);

        return RecipeCollection::create([
            'owner_id' => $owner->id,
            'parent_id' => $parent?->id,
            'name' => $name,
            'slug' => Str::slug($name),
            'path' => $path,
        ]);
    }

    public function rename(RecipeCollection $collection, string $name): RecipeCollection
    {
        $parentPath = Str::contains($collection->path, '/')
            ? Str::beforeLast($collection->path, '/')
            : null;
        $newPath = $parentPath !== null ? $parentPath.'/'.$name : $name;

        if ($newPath === $collection->path) {
            return $collection;
        }

        $this->ensurePathIsAvailable($collection->owner()->firstOrFail(), $newPath, $collection);

        $renamedCollection = DB::transaction(function () use ($collection, $name, $newPath) {
            $oldPath = $collection->path;
            $collection->update([
                'name' => $name,
                'slug' => Str::slug($name),
                'path' => $newPath,
            ]);

            RecipeCollection::query()
                ->where('owner_id', $collection->owner_id)
                ->where('path', 'like', $oldPath.'/%')
                ->get()
                ->each(function (RecipeCollection $descendant) use ($oldPath, $newPath): void {
                    $descendant->update([
                        'path' => $newPath.Str::after($descendant->path, $oldPath),
                    ]);
                });

            return $collection->refresh();
        });

        ReconcileRecipeReferences::dispatch()->afterCommit();

        return $renamedCollection;
    }

    public function delete(RecipeCollection $collection): void
    {
        $collection->delete();
        ReconcileRecipeReferences::dispatch()->afterCommit();
    }

    private function ensurePathIsAvailable(User $owner, string $path, ?RecipeCollection $except = null): void
    {
        $exists = RecipeCollection::query()
            ->where('owner_id', $owner->id)
            ->where('path', $path)
            ->when($except !== null, fn ($query) => $query->whereKeyNot($except?->id))
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'name' => __('app.validation.collection_name_exists'),
            ]);
        }
    }
}
