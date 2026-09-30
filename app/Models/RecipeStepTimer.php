<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Class RecipeStepTimer
 *
 * This class represents a timer attached to a recipe step.
 * It extends the Eloquent Model class and uses the HasUuids trait.
 *
 * Properties:
 *
 * @property string $id The unique identifier for the timer (UUID).
 * @property string $recipe_step_id The id of the recipe step the timer belongs to.
 * @property string|null $name The name of the timer, if any.
 * @property int $duration_seconds The duration of the timer in seconds.
 * @property int $order The sort order of the timer within the step.
 * @property Carbon|null $created_at The timestamp when the timer was created.
 * @property Carbon|null $updated_at The timestamp when the timer was last updated.
 *
 * Relationships:
 * @property RecipeStep|null $step The recipe step the timer belongs to.
 *
 * @mixin Model
 */
class RecipeStepTimer extends Model
{
    use HasUuids;

    protected $fillable = [
        'recipe_step_id',
        'name',
        'duration_seconds',
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
            'duration_seconds' => 'integer',
            'order' => 'integer',
        ];
    }

    /** @return BelongsTo<RecipeStep, $this> */
    public function step(): BelongsTo
    {
        return $this->belongsTo(RecipeStep::class, 'recipe_step_id');
    }
}
