<?php

namespace App\Actions\Translations;

use App\Concerns\TranslationLocaleRules;
use App\Contracts\Translatable;
use App\Data\Translations\CreateTranslationData;
use App\Enums\AppLocale;
use App\Models\Translation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Validation\ValidationException;

/**
 * Creates or updates a translation for a translatable entity, then
 * recalculates the publication status of the entity for the locale.
 */
final class CreateTranslation
{
    /**
     * @param  CreateTranslationData  $data  The data for the new translation
     * @return Translation The created or updated translation.
     *
     * @throws ValidationException When the locale is invalid or the entity does not exist.
     */
    public function execute(CreateTranslationData $data): Translation
    {
        // Accept any well-formed BCP 47 locale (free languages); normalise it
        // and reject the source locale (en-US) and malformed tags.
        $locale = TranslationLocaleRules::normalizeLocaleTag($data->locale);

        if ($locale === null || $locale === AppLocale::default()->value) {
            throw ValidationException::withMessages([
                'locale' => __('app.errors.translation_invalid_locale'),
            ]);
        }

        $data = $data->withLocale($locale);

        $entity = self::resolveEntity($data->translatableType, $data->translatableId);

        if ($entity === null) {
            throw ValidationException::withMessages([
                'translatable_id' => __('app.errors.translation_invalid_entity'),
            ]);
        }

        $translation = $entity->putTranslation(
            field: $data->field,
            locale: $data->locale,
            value: $data->value,
            status: $data->status,
        );

        $entity->recalculateTranslationStatus($data->locale);

        return $translation->refresh();
    }

    /**
     * Resolve the translatable entity from the morph type and id.
     *
     * @return (Model&Translatable)|null
     */
    public static function resolveEntity(string $type, string $id): ?Model
    {
        $class = Relation::getMorphedModel($type);

        if ($class === null || ! is_subclass_of($class, Translatable::class)) {
            return null;
        }

        /** @var (Model&Translatable)|null */
        $model = $class::query()->find($id);

        return $model;
    }
}
