<?php

namespace App\Models;

use App\Enums\HouseholdModule as HouseholdModuleEnum;
use Database\Factories\HouseholdModuleFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Class HouseholdModule
 *
 * This class represents the enabled state and settings of a module for a household.
 * It extends the Eloquent Model class and uses the HasFactory and HasUuids traits.
 *
 * Properties:
 *
 * @property string $id The unique identifier for the module record (UUID).
 * @property string $household_id The id of the household the module belongs to.
 * @property string $module The module identifier.
 * @property bool $enabled Whether the module is enabled for the household.
 * @property array<string, mixed> $settings The settings of the module for the household.
 * @property Carbon|null $created_at The timestamp when the module record was created.
 * @property Carbon|null $updated_at The timestamp when the module record was last updated.
 *
 * Relationships:
 * @property Household|null $household The household the module belongs to.
 *
 * @mixin Model
 */
class HouseholdModule extends Model
{
    /** @use HasFactory<HouseholdModuleFactory> */
    use HasFactory, HasUuids;

    protected $fillable = ['household_id', 'module', 'enabled', 'settings'];

    /**
     * Get the attribute casts for the model.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'settings' => 'array',
        ];
    }

    /** @return BelongsTo<Household, $this> */
    public function household(): BelongsTo
    {
        return $this->belongsTo(Household::class);
    }

    /**
     * Resolve the household module enum for this record's module value.
     */
    public function moduleEnum(): HouseholdModuleEnum
    {
        return HouseholdModuleEnum::from($this->module);
    }
}
