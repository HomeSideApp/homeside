<?php

namespace App\Models;

use Database\Factories\RecipeIngredientFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;

/**
 * Class RecipeIngredient
 *
 * This class represents an ingredient of a recipe, optionally linked to a product.
 * It extends the Eloquent Model class and uses the HasFactory and HasUuids traits.
 *
 * Properties:
 *
 * @property string $id The unique identifier for the ingredient (UUID).
 * @property string $recipe_id The id of the recipe the ingredient belongs to.
 * @property string|null $section_id The id of the recipe section the ingredient belongs to, if any.
 * @property string|null $product_id The id of the product linked to the ingredient, if any.
 * @property string $name The name of the ingredient.
 * @property float|null $quantity The quantity of the ingredient, if any.
 * @property string|null $quantity_text The textual quantity of the ingredient, if any.
 * @property string|null $unit The unit of the ingredient, if any.
 * @property string|null $preparation The preparation instructions of the ingredient, if any.
 * @property string|null $notes Additional notes of the ingredient, if any.
 * @property bool $optional Whether the ingredient is optional.
 * @property string|null $original_text The original text of the ingredient, if any.
 * @property int $order The sort order of the ingredient within the recipe.
 * @property Carbon|null $created_at The timestamp when the ingredient was created.
 * @property Carbon|null $updated_at The timestamp when the ingredient was last updated.
 *
 * Relationships:
 * @property Recipe|null $recipe The recipe the ingredient belongs to.
 * @property RecipeSection|null $section The recipe section the ingredient belongs to, if any.
 * @property Product|null $product The product linked to the ingredient, if any.
 * @property Collection<int, RecipeStep> $steps The recipe steps that use the ingredient.
 *
 * @mixin Model
 */
class RecipeIngredient extends Model
{
    /** @use HasFactory<RecipeIngredientFactory> */
    use HasFactory, HasUuids;

    protected $fillable = [
        'recipe_id',
        'section_id',
        'product_id',
        'name',
        'quantity',
        'quantity_text',
        'unit',
        'preparation',
        'notes',
        'optional',
        'original_text',
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
            'quantity' => 'decimal:2',
            'optional' => 'boolean',
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

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /** @return BelongsToMany<RecipeStep, $this> */
    public function steps(): BelongsToMany
    {
        return $this->belongsToMany(RecipeStep::class, 'recipe_step_ingredients', 'recipe_ingredient_id', 'recipe_step_id')
            ->withPivot('order')
            ->orderBy('order');
    }
}
