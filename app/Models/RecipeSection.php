<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Class RecipeSection
 *
 * This class represents a named section grouping ingredients, steps and cookware of a recipe.
 * It extends the Eloquent Model class and uses the HasUuids trait.
 *
 * Properties:
 *
 * @property string $id The unique identifier for the section (UUID).
 * @property string $recipe_id The id of the recipe the section belongs to.
 * @property string $name The name of the section.
 * @property int $order The sort order of the section within the recipe.
 * @property Carbon|null $created_at The timestamp when the section was created.
 * @property Carbon|null $updated_at The timestamp when the section was last updated.
 *
 * Relationships:
 * @property Recipe|null $recipe The recipe the section belongs to.
 * @property Collection<int, RecipeIngredient> $ingredients The ingredients of the section.
 * @property Collection<int, RecipeStep> $steps The steps of the section.
 * @property Collection<int, RecipeCookware> $cookware The cookware of the section.
 *
 * @mixin Model
 */
class RecipeSection extends Model
{
    use HasUuids;

    protected $fillable = [
        'recipe_id',
        'name',
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
            'order' => 'integer',
        ];
    }

    /** @return BelongsTo<Recipe, $this> */
    public function recipe(): BelongsTo
    {
        return $this->belongsTo(Recipe::class);
    }

    /** @return HasMany<RecipeIngredient, $this> */
    public function ingredients(): HasMany
    {
        return $this->hasMany(RecipeIngredient::class)->orderBy('order');
    }

    /** @return HasMany<RecipeStep, $this> */
    public function steps(): HasMany
    {
        return $this->hasMany(RecipeStep::class)->orderBy('order');
    }

    /** @return HasMany<RecipeCookware, $this> */
    public function cookware(): HasMany
    {
        return $this->hasMany(RecipeCookware::class)->orderBy('order');
    }
}
