<?php

namespace App\Actions\Contacts;

use App\Data\Contacts\ExternalContactData;
use App\Models\Contact;
use App\Models\ContactLabel;
use App\Models\ContactRecord;
use App\Models\ContactSource;
use App\Services\Contacts\ContactPhotoProcessor;
use App\Services\Contacts\SafeRemoteConnectionClient;
use GuzzleHttp\Psr7\HttpFactory;
use Illuminate\Support\Facades\DB;

final class ImportExternalContact
{
    public function __construct(private ContactPhotoProcessor $photos, private SafeRemoteConnectionClient $client) {}

    public function execute(ContactSource $source, ExternalContactData $data): ContactRecord
    {
        $photoBytes = $data->photoBytes ?? $this->downloadPhoto($source, $data);

        return DB::transaction(function () use ($source, $data, $photoBytes): ContactRecord {
            $record = ContactRecord::query()
                ->where('contact_source_id', $source->id)
                ->where('remote_id', $data->remoteId)
                ->first();

            if ($record === null) {
                $contact = Contact::create([
                    'user_id' => $source->user_id,
                    'household_id' => $source->household_id,
                    'type' => $data->organization && ! $data->givenName ? 'organization' : 'person',
                    'display_name' => $data->formattedName,
                ]);
                $record = $contact->records()->create([
                    'contact_source_id' => $source->id,
                    'remote_id' => $data->remoteId,
                    'formatted_name' => $data->formattedName,
                ]);
            }

            $record->update([
                'remote_uid' => $data->uid,
                'remote_href' => $data->href,
                'remote_etag' => $data->etag,
                'formatted_name' => $data->formattedName,
                'given_name' => $data->givenName,
                'family_name' => $data->familyName,
                'additional_name' => $data->additionalName,
                'nickname' => $data->nickname,
                'organization' => $data->organization,
                'job_title' => $data->jobTitle,
                'birthday' => $data->birthday,
                'notes' => $data->notes,
                'remote_deleted_at' => null,
            ]);

            foreach (['emails', 'phones', 'addresses', 'urls'] as $kind) {
                DB::table('contact_'.$kind)->where('contact_record_id', $record->id)->delete();
                foreach ($data->{$kind} as $value) {
                    DB::table('contact_'.$kind)->insert([
                        'contact_record_id' => $record->id,
                        'value' => $value->value,
                        'type' => $value->type,
                        'preferred' => $value->preferred,
                    ]);
                }
            }

            DB::table('contact_record_dates')->where('contact_record_id', $record->id)->delete();
            foreach ($data->dates as $date) {
                DB::table('contact_record_dates')->insert([
                    'contact_record_id' => $record->id,
                    'kind' => $date['kind'],
                    'label' => $date['label'],
                    'value' => $date['value'],
                    'value_type' => $date['value_type'],
                ]);
            }

            DB::table('contact_record_relations')->where('contact_record_id', $record->id)->delete();
            foreach ($data->relations as $relation) {
                DB::table('contact_record_relations')->insert([
                    'contact_record_id' => $record->id,
                    'type' => $relation['type'],
                    'name' => $relation['name'],
                    'external_value' => $relation['external_value'],
                ]);
            }

            if ($photoBytes !== null) {
                try {
                    $this->photos->store($record, $photoBytes);
                } catch (\RuntimeException $exception) {
                    report($exception);
                }
            }

            if ($record->contact->preferred_record_id === null) {
                $record->contact->update(['preferred_record_id' => $record->id]);
            }

            $labelIds = [];
            foreach ($data->categories as $name) {
                $labelIds[] = ContactLabel::query()->firstOrCreate([
                    'user_id' => $source->user_id,
                    'household_id' => $source->household_id,
                    'name' => $name,
                ])->id;
            }
            $metadata = $record->provider_metadata ?? [];
            $metadata['category_label_ids'] = $labelIds;
            $record->update(['provider_metadata' => $metadata]);
            $this->syncImportedLabels($record->contact);

            return $record;
        });
    }

    public function clearImportedLabels(Contact $contact): void
    {
        $this->syncImportedLabels($contact);
    }

    private function syncImportedLabels(Contact $contact): void
    {
        $labelIds = $contact->records()->whereNull('remote_deleted_at')
            ->get()->flatMap(fn (ContactRecord $record): array => $record->provider_metadata['category_label_ids'] ?? [])
            ->unique()->values()->all();
        $selected = array_fill_keys($labelIds, true);
        $existing = $contact->labels()->get();

        foreach ($existing as $label) {
            if (! $label->pivot->imported || isset($selected[$label->id])) {
                continue;
            }

            if ($label->pivot->manual) {
                $contact->labels()->updateExistingPivot($label->id, ['imported' => false]);
            } else {
                $contact->labels()->detach($label->id);
            }
        }

        foreach ($labelIds as $labelId) {
            $assigned = $existing->firstWhere('id', $labelId);
            if ($assigned === null) {
                $contact->labels()->attach($labelId, ['manual' => false, 'imported' => true]);
            } elseif (! $assigned->pivot->imported) {
                $contact->labels()->updateExistingPivot($labelId, ['imported' => true]);
            }
        }
    }

    private function downloadPhoto(ContactSource $source, ExternalContactData $data): ?string
    {
        if ($data->photoUri === null || $data->href === null) {
            return null;
        }

        $serverUrl = $source->provider_configuration['server_url'] ?? null;
        if (! is_string($serverUrl)) {
            return null;
        }

        try {
            $url = \Sabre\Uri\resolve($data->href, $data->photoUri);
            $credentials = $source->encrypted_credentials ?? [];
            $request = (new HttpFactory)->createRequest('GET', $url);
            if (isset($credentials['username'], $credentials['password'])) {
                $request = $request->withHeader('Authorization', 'Basic '.base64_encode($credentials['username'].':'.$credentials['password']));
            }

            $response = $this->client->forOrigin($serverUrl)->sendRequest($request);
            if ($response->getStatusCode() !== 200) {
                return null;
            }
            $bytes = (string) $response->getBody();

            return strlen($bytes) <= 3 * 1024 * 1024 ? $bytes : null;
        } catch (\Exception) {
            return null;
        }
    }
}
