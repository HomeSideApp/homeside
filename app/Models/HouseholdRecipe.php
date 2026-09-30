<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Class HouseholdRecipe
 *
 * This class represents the pivot recording that a recipe has been shared with a household.
 * It extends the Eloquent Model class and uses the HasUuids trait.
 *
 * Properties:
 *
 * @property string $id The unique identifier for the pivot record (UUID).
 * @property string $household_id The id of the household the recipe was shared with.
 * @property string $recipe_id The id of the recipe shared with the household.
 * @property string $shared_by The id of the user who shared the recipe.
 * @property bool $is_favorite Whether the recipe is marked as a favorite for the household.
 * @property string|null $notes Notes about the shared recipe, if any.
 * @property Carbon|null $created_at The timestamp when the record was created.
 * @property Carbon|null $updated_at The timestamp when the record was last updated.
 *
 * Relationships:
 * @property Household|null $household The household the recipe was shared with.
 * @property Recipe|null $recipe The recipe shared with the household.
 * @property User|null $sharedBy The user who shared the recipe.
 *
 * @mixin Model
 */
class HouseholdRecipe extends Model
{
    use HasUuids;

    protected $fillable = [
        'household_id',
        'recipe_id',
        'shared_by',
        'is_favorite',
        'notes',
    ];

    /**
     * Get the attribute casts for the model.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_favorite' => 'boolean',
        ];
    }

    /** @return BelongsTo<Household, $this> */
    public function household(): BelongsTo
    {
        return $this->belongsTo(Household::class);
    }

    /** @return BelongsTo<Recipe, $this> */
    public function recipe(): BelongsTo
    {
        return $this->belongsTo(Recipe::class);
    }

    /** @return BelongsTo<User, $this> */
    public function sharedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'shared_by');
    }
}
