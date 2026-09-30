<?php

namespace App\Models;

use App\Enums\TranslationFieldStatus;
use Database\Factories\TranslationFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * Class Translation
 *
 * This class represents a single translated field of a translatable entity
 * (category, product, store, tag) for a specific locale. It uses a polymorphic
 * relation so a single table stores translations for every translatable model.
 *
 * Properties:
 *
 * @property string $id The unique identifier for the translation (UUID).
 * @property string $translatable_type The morph map alias of the translated entity.
 * @property string $translatable_id The unique identifier of the translated entity (UUID).
 * @property string $locale The BCP 47 locale of the translation (e.g. en-US, es-ES).
 * @property string $field The name of the translated field (e.g. name).
 * @property string $value The translated text.
 * @property TranslationFieldStatus $status The review status of the translation field.
 * @property Carbon|null $created_at The timestamp when the translation was created.
 * @property Carbon|null $updated_at The timestamp when the translation was last updated.
 *
 * Relationships:
 * @property Category|Product|Store|Tag $translatable The translated entity.
 *
 * @mixin Model
 */
class Translation extends Model
{
    /** @use HasFactory<TranslationFactory> */
    use HasFactory, HasUuids;

    protected $fillable = ['translatable_type', 'translatable_id', 'locale', 'field', 'value', 'status'];

    /**
     * Get the attribute casts for the model.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => TranslationFieldStatus::class,
        ];
    }

    /** @return MorphTo<Model, $this> */
    public function translatable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Scope a query to translations of a specific locale.
     *
     * @param  Builder<self>  $query
     */
    public function scopeForLocale(Builder $query, string $locale): void
    {
        $query->where('locale', $locale);
    }

    /**
     * Scope a query to translations of a specific morph type.
     *
     * @param  Builder<self>  $query
     */
    public function scopeForType(Builder $query, string $type): void
    {
        $query->where('translatable_type', $type);
    }

    /**
     * Scope a query to translations of a specific field.
     *
     * @param  Builder<self>  $query
     */
    public function scopeForField(Builder $query, string $field): void
    {
        $query->where('field', $field);
    }

    /**
     * Scope a query to translations with a specific status.
     *
     * @param  Builder<self>  $query
     */
    public function scopeWithStatus(Builder $query, TranslationFieldStatus $status): void
    {
        $query->where('status', $status->value);
    }
}
