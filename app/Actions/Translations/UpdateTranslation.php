<?php

namespace App\Actions\Translations;

use App\Contracts\Translatable;
use App\Data\Translations\UpdateTranslationData;
use App\Models\Translation;
use Illuminate\Database\Eloquent\Model;

/**
 * Updates an existing translation and recalculates the publication
 * status of its entity for the translation locale.
 */
final class UpdateTranslation
{
    /**
     * @param  Translation  $translation  The translation to update
     * @param  UpdateTranslationData  $data  The new translation data
     * @return Translation The updated translation.
     */
    public function execute(Translation $translation, UpdateTranslationData $data): Translation
    {
        $translation->update([
            'value' => $data->value,
            'status' => $data->status ?? $translation->status,
        ]);

        /** @var (Model&Translatable)|null $entity */
        $entity = $translation->translatable;

        if ($entity !== null) {
            $entity->recalculateTranslationStatus($translation->locale);
        }

        return $translation->refresh();
    }
}
