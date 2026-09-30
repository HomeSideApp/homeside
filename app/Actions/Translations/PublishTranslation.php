<?php

namespace App\Actions\Translations;

use App\Data\Translations\PublishTranslationData;
use App\Enums\TranslationEntityStatus;
use App\Models\TranslationStatus;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * Publishes or unpublishes the translation of an entity for a locale.
 * Publishing requires the entity to be complete for that locale; the
 * completeness is never bypassed so users never see partial translations.
 */
final class PublishTranslation
{
    /**
     * @param  PublishTranslationData  $data  The publish/unpublish request data
     *
     * @throws ValidationException When publishing and the entity translation is not complete.
     */
    public function execute(PublishTranslationData $data): void
    {
        $entity = CreateTranslation::resolveEntity($data->translatableType, $data->translatableId);

        if ($entity === null) {
            throw ValidationException::withMessages([
                'translatable_id' => __('app.errors.translation_invalid_entity'),
            ]);
        }

        /** @var TranslationStatus $status */
        $status = TranslationStatus::query()->firstOrCreate(
            [
                'translatable_type' => $entity->getMorphClass(),
                'translatable_id' => $entity->getKey(),
                'locale' => $data->locale,
            ],
            ['status' => TranslationEntityStatus::Incomplete->value],
        );

        if ($data->publish) {
            if (! $entity->isComplete($data->locale)) {
                throw ValidationException::withMessages([
                    'publish' => __('app.errors.translation_not_complete'),
                ]);
            }

            $status->status = TranslationEntityStatus::Published;
            $status->published_at = Carbon::now();
        } else {
            $status->status = TranslationEntityStatus::Incomplete;
            $status->published_at = null;
        }

        $status->save();
    }
}
