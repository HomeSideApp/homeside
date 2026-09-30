<?php

namespace App\Actions\Translations;

use App\Contracts\Translatable;
use App\Models\Translation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\DB;

/**
 * Deletes an existing translation and recalculates the publication
 * status of its entity for the translation locale.
 */
final class DeleteTranslation
{
    /**
     * @param  Translation  $translation  The translation to delete
     */
    public function execute(Translation $translation): void
    {
        DB::transaction(function () use ($translation): void {
            $translatableType = $translation->translatable_type;
            $translatableId = $translation->translatable_id;
            $locale = $translation->locale;

            $translation->delete();

            $entityClass = Relation::getMorphedModel($translatableType);

            /** @var (Model&Translatable)|null $entity */
            $entity = $entityClass !== null ? $entityClass::find($translatableId) : null;

            if ($entity !== null) {
                $entity->recalculateTranslationStatus($locale);
            }
        });
    }
}
