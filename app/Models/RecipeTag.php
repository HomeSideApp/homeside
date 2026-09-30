<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Class RecipeTag
 *
 * This class represents a single tag attached to a recipe.
 * It extends the Eloquent Model class and uses the HasUuids trait.
 *
 * Properties:
 *
 * @property string $id The unique identifier for the tag (UUID).
 * @property string $recipe_id The id of the recipe the tag belongs to.
 * @property string $name The name of the tag.
 * @property Carbon|null $created_at The timestamp when the tag was created.
 * @property Carbon|null $updated_at The timestamp when the tag was last updated.
 *
 * Relationships:
 * @property Recipe|null $recipe The recipe the tag belongs to.
 *
 * @mixin Model
 */
class RecipeTag extends Model
{
    use HasUuids;

    protected $fillable = [
        'recipe_id',
        'name',
    ];

    /** @return BelongsTo<Recipe, $this> */
    public function recipe(): BelongsTo
    {
        return $this->belongsTo(Recipe::class);
    }
}
