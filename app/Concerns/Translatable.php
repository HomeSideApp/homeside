<?php

namespace App\Concerns;

use App\Contracts\Translatable as TranslatableContract;
use App\Enums\AppLocale;
use App\Enums\TranslationEntityStatus;
use App\Enums\TranslationFieldStatus;
use App\Models\Translation;
use App\Models\TranslationStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\Relation;

/**
 * Trait Translatable
 *
 * Gives a model the ability to hold translations in the central polymorphic
 * `translations` table, with a publication gate so translations are only
 * exposed to users when the entity + locale is `published`. Otherwise the
 * source column value is returned, which prevents showing translations
 * "in pieces".
 *
 * Models using this trait must define:
 *
 *     /** @var list<string> #/
 *     protected array $translatable = ['name'];
 *
 * @property Collection<int, Translation> $translations The translations of the entity.
 * @property Collection<int, TranslationStatus> $translationStatus The publication statuses of the entity by locale.
 *
 * @mixin Model
 */
trait Translatable
{
    /**
     * The translatable fields of the model.
     *
     * @var list<string>
     */
    protected array $translatable = ['name'];

    /** @return MorphMany<Translation, $this> */
    public function translations(): MorphMany
    {
        return $this->morphMany(Translation::class, 'translatable');
    }

    /**
     * Publication status of the entity per locale. A MorphMany because there is
     * one row per locale; filter by locale in memory or with the scopes.
     *
     * @return MorphMany<TranslationStatus, $this>
     */
    public function translationStatus(): MorphMany
    {
        return $this->morphMany(TranslationStatus::class, 'translatable');
    }

    /**
     * The morph map alias of this model (e.g. 'category', 'product').
     */
    public function translatableType(): string
    {
        /** @var class-string<static> $class */
        $class = static::class;

        /** @var string */
        return Relation::getMorphAlias($class);
    }

    /**
     * The translatable field names of this model.
     *
     * @return list<string>
     */
    public function getTranslatableAttributes(): array
    {
        return $this->translatable;
    }

    /**
     * Whether the entity has a published translation for the given locale.
     */
    public function isPublished(string $locale): bool
    {
        if ($this->relationLoaded('translationStatus')) {
            $statuses = $this->getRelation('translationStatus');
        } else {
            $statuses = $this->translationStatus()->forLocale($locale)->get();
        }

        return $statuses
            ->first(fn (TranslationStatus $status): bool => $status->locale === $locale)
            ?->status === TranslationEntityStatus::Published;
    }

    /**
     * Whether every translatable field has a non-empty translation for the given locale.
     */
    public function isComplete(string $locale): bool
    {
        if ($this->relationLoaded('translations')) {
            $translations = $this->getRelation('translations')
                ->filter(fn (Translation $t): bool => $t->locale === $locale);
        } else {
            $translations = $this->translations()->forLocale($locale)->get();
        }

        $byField = $translations->keyBy('field');

        foreach ($this->getTranslatableAttributes() as $field) {
            /** @var Translation|null $translation */
            $translation = $byField->get($field);

            if ($translation === null || trim((string) $translation->value) === '') {
                return false;
            }
        }

        return true;
    }

    /**
     * The effective value the user sees for a field, following this hierarchy:
     * 1. Published translation for the active locale.
     * 2. Published translation for the fallback locale (en-US).
     * 3. The source column value.
     *
     * Works with eagerly loaded relations (`withTranslationData()`) and falls
     * back to `loadMissing` otherwise.
     */
    public function localized(string $field): ?string
    {
        $source = $this->getAttribute($field);

        $this->loadMissing(['translations', 'translationStatus']);

        /** @var Collection<int, Translation> $translations */
        $translations = $this->getRelation('translations');

        $activeLocale = AppLocale::resolve(app()->getLocale())->value;
        $fallbackLocale = AppLocale::resolve(config('app.fallback_locale'))->value;

        $candidates = array_values(array_unique([$activeLocale, $fallbackLocale]));

        foreach ($candidates as $candidate) {
            if (! $this->isPublished($candidate)) {
                continue;
            }

            $translation = $translations
                ->first(fn (Translation $t): bool => $t->locale === $candidate && $t->field === $field);

            if ($translation !== null && trim((string) $translation->value) !== '') {
                return $translation->value;
            }
        }

        return $source === null ? null : (string) $source;
    }

    /**
     * Recalculate the per-locale publication status of the entity after
     * translations are created, updated or deleted.
     *
     * - Creates the status row if it does not exist (firstOrCreate, incomplete).
     * - Sets complete/incomplete from coverage, never touching `published` by itself.
     * - If the row is published but coverage is lost, it downgrades to incomplete
     *   and clears `published_at`.
     */
    public function recalculateTranslationStatus(string $locale): void
    {
        $isComplete = $this->isComplete($locale);

        /** @var TranslationStatus $status */
        $status = $this->translationStatus()->firstOrCreate(
            ['locale' => $locale],
            ['status' => TranslationEntityStatus::Incomplete->value],
        );

        $wasPublished = $status->status === TranslationEntityStatus::Published;

        if ($wasPublished) {
            // A published status stays published while coverage is kept; it only
            // downgrades (and clears published_at) when coverage is lost.
            if ($isComplete) {
                return;
            }

            $status->status = TranslationEntityStatus::Incomplete;
            $status->published_at = null;
        } else {
            $status->status = $isComplete
                ? TranslationEntityStatus::Complete
                : TranslationEntityStatus::Incomplete;
        }

        $status->save();
    }

    /**
     * Create or update a single field translation for the entity, resolving
     * the unique constraint (type, id, locale, field) via updateOrCreate.
     */
    public function putTranslation(
        string $field,
        string $locale,
        string $value,
        TranslationFieldStatus $status,
    ): Translation {
        /** @var Translation $translation */
        $translation = $this->translations()->updateOrCreate(
            [
                'locale' => $locale,
                'field' => $field,
            ],
            [
                'value' => $value,
                'status' => $status,
            ],
        );

        return $translation;
    }

    /**
     * Eager load the translations and their publication status to avoid N+1
     * queries when serializing collections with `localized()`.
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeWithTranslationData(Builder $query): Builder
    {
        return $query->with(['translations', 'translationStatus']);
    }

    /**
     * Find a translatable entity by its morph type and id, resolving the
     * morph alias through the registered morph map.
     *
     * @return (Model&Translatable)|null
     */
    public static function findTranslatable(string $type, string $id): ?Model
    {
        $class = Relation::getMorphedModel($type);

        if ($class === null || ! is_subclass_of($class, TranslatableContract::class)) {
            return null;
        }

        /** @var (Model&Translatable)|null $model */
        $model = $class::query()->find($id);

        return $model;
    }
}
