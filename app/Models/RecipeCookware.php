<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;

/**
 * Class RecipeCookware
 *
 * This class represents a piece of cookware used in a recipe, optionally linked to a section.
 * It extends the Eloquent Model class and uses the HasUuids trait.
 *
 * Properties:
 *
 * @property string $id The unique identifier for the cookware (UUID).
 * @property string $recipe_id The id of the recipe the cookware belongs to.
 * @property string|null $section_id The id of the recipe section the cookware belongs to, if any.
 * @property string $name The name of the cookware.
 * @property string|null $type The type of the cookware, if any.
 * @property int|null $quantity The quantity of the cookware, if any.
 * @property string|null $quantity_text The textual quantity of the cookware, if any.
 * @property string|null $unit The unit of the cookware, if any.
 * @property int $order The sort order of the cookware within the recipe.
 * @property Carbon|null $created_at The timestamp when the cookware was created.
 * @property Carbon|null $updated_at The timestamp when the cookware was last updated.
 *
 * Relationships:
 * @property Recipe|null $recipe The recipe the cookware belongs to.
 * @property RecipeSection|null $section The recipe section the cookware belongs to, if any.
 * @property Collection<int, RecipeStep> $steps The recipe steps that use the cookware.
 *
 * @mixin Model
 */
class RecipeCookware extends Model
{
    use HasUuids;

    protected $fillable = [
        'recipe_id',
        'section_id',
        'name',
        'type',
        'quantity',
        'quantity_text',
        'unit',
        'order',
    ];

    /**
     * Get the attribute casts for the model.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'order' => 'integer',
        ];
    }

    /** @return BelongsTo<Recipe, $this> */
    public function recipe(): BelongsTo
    {
        return $this->belongsTo(Recipe::class);
    }

    /** @return BelongsTo<RecipeSection, $this> */
    public function section(): BelongsTo
    {
        return $this->belongsTo(RecipeSection::class);
    }

    /** @return BelongsToMany<RecipeStep, $this> */
    public function steps(): BelongsToMany
    {
        return $this->belongsToMany(RecipeStep::class, 'recipe_step_cookware', 'recipe_cookware_id', 'recipe_step_id')
            ->withPivot('order')
            ->orderBy('order');
    }
}
