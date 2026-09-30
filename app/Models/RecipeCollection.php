<?php

namespace App\Models;

use Database\Factories\RecipeCollectionFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Class RecipeCollection
 *
 * This class represents a collection (folder) that organises a user's recipes hierarchically.
 * It extends the Eloquent Model class and uses the HasFactory and HasUuids traits.
 *
 * Properties:
 *
 * @property string $id The unique identifier for the collection (UUID).
 * @property string $owner_id The id of the user who owns the collection.
 * @property string|null $parent_id The id of the parent collection, if any.
 * @property string $name The name of the collection.
 * @property string $slug The unique slug of the collection.
 * @property string|null $path The hierarchical path of the collection, if any.
 * @property Carbon|null $created_at The timestamp when the collection was created.
 * @property Carbon|null $updated_at The timestamp when the collection was last updated.
 *
 * Relationships:
 * @property User|null $owner The user who owns the collection.
 * @property RecipeCollection|null $parent The parent collection, if any.
 * @property Collection<int, RecipeCollection> $children The child collections of this collection.
 * @property Collection<int, Recipe> $recipes The recipes in this collection.
 *
 * @mixin Model
 */
class RecipeCollection extends Model
{
    /** @use HasFactory<RecipeCollectionFactory> */
    use HasFactory, HasUuids;

    protected $fillable = ['owner_id', 'parent_id', 'name', 'slug', 'path'];

    /** @return BelongsTo<User, $this> */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /** @return BelongsTo<RecipeCollection, $this> */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /** @return HasMany<RecipeCollection, $this> */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('name');
    }

    /** @return HasMany<Recipe, $this> */
    public function recipes(): HasMany
    {
        return $this->hasMany(Recipe::class, 'collection_id');
    }
}
