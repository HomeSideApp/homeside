<?php

namespace App\Contracts;

use App\Enums\TranslationFieldStatus;
use App\Models\Translation;
use Illuminate\Database\Eloquent\Model;

/**
 * Contract implemented by models using the App\Concerns\Translatable trait.
 *
 * Lets actions, controllers and queries reference translatable entities as
 * `Model&Translatable` intersections that PHPStan can resolve. Relation
 * methods (`translations`, `translationStatus`) are intentionally omitted:
 * they are covariant on the declaring model and callers that need them
 * should reference the concrete model classes.
 *
 * @phpstan-require-extends Model
 */
interface Translatable
{
    /**
     * The morph map alias of this model (e.g. 'category', 'product').
     */
    public function translatableType(): string;

    /**
     * The translatable field names of this model.
     *
     * @return list<string>
     */
    public function getTranslatableAttributes(): array;

    /**
     * Whether the entity has a published translation for the given locale.
     */
    public function isPublished(string $locale): bool;

    /**
     * Whether every translatable field has a non-empty translation for the given locale.
     */
    public function isComplete(string $locale): bool;

    /**
     * The effective value the user sees for a field (published translation or source).
     */
    public function localized(string $field): ?string;

    /**
     * Recalculate the per-locale publication status of the entity.
     */
    public function recalculateTranslationStatus(string $locale): void;

    /**
     * Create or update a single field translation for the entity.
     */
    public function putTranslation(
        string $field,
        string $locale,
        string $value,
        TranslationFieldStatus $status,
    ): Translation;
}
