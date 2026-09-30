<?php

namespace App\Actions\Contacts;

use App\Models\Contact;
use App\Models\ContactLabel;
use App\Models\ContactRecord;
use App\Models\ContactSource;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

final class MarkFavoriteContacts
{
    /** @param Collection<int, Contact> $contacts */
    public function execute(Collection $contacts, User $user): void
    {
        if ($contacts->isEmpty()) {
            return;
        }

        $favoriteLabelsBySource = ContactSource::query()->visibleTo($user)
            ->get(['id', 'provider_configuration'])
            ->mapWithKeys(fn (ContactSource $source): array => [$source->id => mb_strtolower($source->favoriteLabel())]);
        $sourceIdsByContact = ContactRecord::query()
            ->whereIn('contact_id', $contacts->modelKeys())
            ->whereNotNull('contact_source_id')
            ->whereNull('remote_deleted_at')
            ->get(['contact_id', 'contact_source_id'])
            ->groupBy('contact_id');

        foreach ($contacts as $contact) {
            $favoriteNames = ($sourceIdsByContact->get($contact->id) ?? collect())
                ->map(fn (ContactRecord $record): ?string => $favoriteLabelsBySource->get($record->contact_source_id))
                ->filter()
                ->unique()
                ->all();

            $contact->setAttribute('is_favorite', $contact->labels->contains(
                fn (ContactLabel $label): bool => ($label->pivot->manual && mb_strtolower($label->name) === mb_strtolower(ContactSource::DEFAULT_FAVORITE_LABEL))
                    || in_array(mb_strtolower($label->name), $favoriteNames, true),
            ));
        }
    }
}
