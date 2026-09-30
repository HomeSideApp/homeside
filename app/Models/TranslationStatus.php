<?php

namespace App\Models;

use App\Enums\TranslationEntityStatus;
use Database\Factories\TranslationStatusFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * Class TranslationStatus
 *
 * This class represents the translation status (incomplete | complete | published)
 * of a translatable entity (category, product, store, tag) for a specific locale.
 * It uses a polymorphic relation so a single table stores statuses for every
 * translatable model. Only entities with a `published` status are exposed to
 * users in that locale; any other status falls back to the source column.
 *
 * Properties:
 *
 * @property string $id The unique identifier for the translation status (UUID).
 * @property string $translatable_type The morph map alias of the translatable entity.
 * @property string $translatable_id The unique identifier of the translatable entity (UUID).
 * @property string $locale The BCP 47 locale of the status (e.g. en-US, es-ES).
 * @property TranslationEntityStatus $status The publication status for this entity and locale.
 * @property Carbon|null $published_at The timestamp when the translation was published, if any.
 * @property Carbon|null $created_at The timestamp when the status was created.
 * @property Carbon|null $updated_at The timestamp when the status was last updated.
 *
 * Relationships:
 * @property Category|Product|Store|Tag $translatable The translatable entity.
 *
 * @mixin Model
 */
class TranslationStatus extends Model
{
    /** @use HasFactory<TranslationStatusFactory> */
    use HasFactory, HasUuids;

    protected $fillable = ['translatable_type', 'translatable_id', 'locale', 'status', 'published_at'];

    /**
     * Get the attribute casts for the model.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => TranslationEntityStatus::class,
            'published_at' => 'datetime',
        ];
    }

    /** @return MorphTo<Model, $this> */
    public function translatable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Scope a query to statuses of a specific locale.
     *
     * @param  Builder<self>  $query
     */
    public function scopeForLocale(Builder $query, string $locale): void
    {
        $query->where('locale', $locale);
    }

    /**
     * Scope a query to statuses of a specific morph type.
     *
     * @param  Builder<self>  $query
     */
    public function scopeForType(Builder $query, string $type): void
    {
        $query->where('translatable_type', $type);
    }

    /**
     * Scope a query to published statuses.
     *
     * @param  Builder<self>  $query
     */
    public function scopePublished(Builder $query): void
    {
        $query->where('status', TranslationEntityStatus::Published->value);
    }
}
