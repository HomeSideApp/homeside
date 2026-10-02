<?php

namespace App\Actions\Contacts;

use App\Models\Contact;
use App\Models\ContactSource;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Support\Facades\DB;

final class MarkFavoriteContacts
{
    /** @param Collection<int, Contact> $contacts */
    public function execute(Collection $contacts, User $user): void
    {
        if ($contacts->isEmpty()) {
            return;
        }

        $favoriteContactIds = $this->idsFor($user)->flip();

        foreach ($contacts as $contact) {
            $contact->setAttribute('is_favorite', $favoriteContactIds->has($contact->id));
        }
    }

    /** @return SupportCollection<int, string> */
    public function idsFor(User $user): SupportCollection
    {
        $favoriteLabelsBySource = ContactSource::query()->visibleTo($user)
            ->get(['id', 'provider_configuration'])
            ->mapWithKeys(fn (ContactSource $source): array => [$source->id => mb_strtolower($source->favoriteLabel())]);

        $contactIds = DB::table('contact_contact_label as contact_labels_link')
            ->join('contact_labels', 'contact_labels.id', '=', 'contact_labels_link.contact_label_id')
            ->join('contacts', 'contacts.id', '=', 'contact_labels_link.contact_id')
            ->where('contact_labels_link.manual', true)
            ->whereRaw('LOWER(contact_labels.name) = ?', [mb_strtolower(ContactSource::DEFAULT_FAVORITE_LABEL)])
            ->whereNull('contacts.deleted_at')
            ->where(fn (QueryBuilder $contacts): QueryBuilder => $contacts
                ->where('contacts.user_id', $user->id)
                ->orWhereIn('contacts.household_id', $user->households()->select('households.id')))
            ->pluck('contact_labels_link.contact_id');

        foreach ($favoriteLabelsBySource as $sourceId => $favoriteLabel) {
            $contactIds->push(...DB::table('contact_records')
                ->join('contact_contact_label as contact_labels_link', 'contact_labels_link.contact_id', '=', 'contact_records.contact_id')
                ->join('contact_labels', 'contact_labels.id', '=', 'contact_labels_link.contact_label_id')
                ->where('contact_records.contact_source_id', $sourceId)
                ->whereNull('contact_records.remote_deleted_at')
                ->whereRaw('LOWER(contact_labels.name) = ?', [$favoriteLabel])
                ->pluck('contact_records.contact_id'));
        }

        return $contactIds->unique()->values();
    }
}
