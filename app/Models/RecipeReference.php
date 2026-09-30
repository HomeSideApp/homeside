<?php

namespace App\Models;

use Database\Factories\RecipeReferenceFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Class RecipeReference
 *
 * This class represents a reference from one recipe (or step) to another recipe, used to
 * link ingredients that resolve to recipes within a Cooklang document.
 * It extends the Eloquent Model class and uses the HasFactory and HasUuids traits.
 *
 * Properties:
 *
 * @property string $id The unique identifier for the reference (UUID).
 * @property string $recipe_id The id of the recipe the reference belongs to.
 * @property string|null $recipe_step_id The id of the recipe step the reference belongs to, if any.
 * @property string $referenced_recipe_id The id of the recipe being referenced.
 * @property string|null $path The path of the referenced recipe, if any.
 * @property float|null $quantity The quantity of the referenced ingredient, if any.
 * @property string|null $unit The unit of the referenced ingredient, if any.
 * @property int $order The sort order of the reference within the recipe.
 * @property Carbon|null $created_at The timestamp when the reference was created.
 * @property Carbon|null $updated_at The timestamp when the reference was last updated.
 *
 * Relationships:
 * @property Recipe|null $recipe The recipe the reference belongs to.
 * @property RecipeStep|null $step The recipe step the reference belongs to, if any.
 * @property Recipe|null $referencedRecipe The recipe being referenced.
 *
 * @mixin Model
 */
class RecipeReference extends Model
{
    /** @use HasFactory<RecipeReferenceFactory> */
    use HasFactory, HasUuids;

    protected $fillable = ['recipe_id', 'recipe_step_id', 'referenced_recipe_id', 'path', 'quantity', 'unit', 'order'];

    protected function casts(): array
    {
        return ['quantity' => 'float', 'order' => 'integer'];
    }

    /** @return BelongsTo<Recipe, $this> */
    public function recipe(): BelongsTo
    {
        return $this->belongsTo(Recipe::class);
    }

    /** @return BelongsTo<RecipeStep, $this> */
    public function step(): BelongsTo
    {
        return $this->belongsTo(RecipeStep::class, 'recipe_step_id');
    }

    /** @return BelongsTo<Recipe, $this> */
    public function referencedRecipe(): BelongsTo
    {
        return $this->belongsTo(Recipe::class, 'referenced_recipe_id');
    }
}
