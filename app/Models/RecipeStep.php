<?php

namespace App\Models;

use Database\Factories\RecipeStepFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Class RecipeStep
 *
 * This class represents a single cooking step of a recipe.
 * It extends the Eloquent Model class and uses the HasFactory and HasUuids traits.
 *
 * Properties:
 *
 * @property string $id The unique identifier for the step (UUID).
 * @property string $recipe_id The id of the recipe the step belongs to.
 * @property string|null $section_id The id of the recipe section the step belongs to, if any.
 * @property string $description The description of the step.
 * @property string|null $image_path The path of the step image, if any.
 * @property int $order The sort order of the step within the recipe.
 * @property Carbon|null $created_at The timestamp when the step was created.
 * @property Carbon|null $updated_at The timestamp when the step was last updated.
 *
 * Relationships:
 * @property Recipe|null $recipe The recipe the step belongs to.
 * @property RecipeSection|null $section The recipe section the step belongs to, if any.
 * @property Collection<int, RecipeIngredient> $ingredients The ingredients used by the step.
 * @property Collection<int, RecipeCookware> $cookware The cookware used by the step.
 * @property Collection<int, RecipeStepTimer> $timers The timers of the step.
 * @property Collection<int, RecipeReference> $recipeReferences The references of the step.
 *
 * @mixin Model
 */
class RecipeStep extends Model
{
    /** @use HasFactory<RecipeStepFactory> */
    use HasFactory, HasUuids;

    protected $fillable = [
        'recipe_id',
        'section_id',
        'description',
        'image_path',
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

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $array = parent::toArray();

        $array['image_url'] = ! empty($array['image_path'])
            ? route('images.show', ['type' => 'recipe_step', 'uuid' => $this->recipe_id, 'step' => $this->id])
            : null;

        return $array;
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

    /** @return BelongsToMany<RecipeIngredient, $this> */
    public function ingredients(): BelongsToMany
    {
        return $this->belongsToMany(RecipeIngredient::class, 'recipe_step_ingredients', 'recipe_step_id', 'recipe_ingredient_id')
            ->withPivot('order')
            ->orderBy('order');
    }

    /** @return BelongsToMany<RecipeCookware, $this> */
    public function cookware(): BelongsToMany
    {
        return $this->belongsToMany(RecipeCookware::class, 'recipe_step_cookware', 'recipe_step_id', 'recipe_cookware_id')
            ->withPivot('order')
            ->orderBy('order');
    }

    /** @return HasMany<RecipeStepTimer, $this> */
    public function timers(): HasMany
    {
        return $this->hasMany(RecipeStepTimer::class)->orderBy('order');
    }

    /** @return HasMany<RecipeReference, $this> */
    public function recipeReferences(): HasMany
    {
        return $this->hasMany(RecipeReference::class)->orderBy('order');
    }
}
