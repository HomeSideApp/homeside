<?php

namespace App\Services\Contacts\Providers;

use App\Contracts\Contacts\ContactProvider;
use App\Data\Contacts\ContactProviderCapabilitiesData;
use App\Data\Contacts\ContactSyncCursorData;
use App\Data\Contacts\ContactSyncResultData;
use App\Data\Contacts\ContactValueData;
use App\Data\Contacts\ExternalCollectionData;
use App\Data\Contacts\ExternalContactData;
use App\Models\ContactCollection;
use App\Models\ContactSource;
use Illuminate\Support\Str;
use RuntimeException;

final class GooglePeopleContactProvider implements ContactProvider
{
    public function __construct(private GooglePeopleClient $client) {}

    public function capabilities(): ContactProviderCapabilitiesData
    {
        return new ContactProviderCapabilitiesData(true, false, true, true, true, true, true);
    }

    public function collections(ContactSource $source): array
    {
        $this->client->get($source, 'people/me/connections', [
            'pageSize' => 1,
            'personFields' => 'names',
            'sources[]' => 'READ_SOURCE_TYPE_CONTACT',
        ])->throw();

        return [new ExternalCollectionData('all', 'Todos los contactos', 'people/me/connections', true)];
    }

    public function synchronize(ContactCollection $collection, ?ContactSyncCursorData $cursor): ContactSyncResultData
    {
        $source = $collection->source;
        if ($source === null) {
            throw new RuntimeException('Google contact collection has no source.');
        }

        $state = $cursor === null ? [] : $cursor->state;
        $pageToken = $state['page_token'] ?? null;
        $full = $cursor?->value === null || ($state['full'] ?? false) === true;
        $runId = $full ? ($state['run_id'] ?? (string) Str::uuid()) : null;
        $query = [
            'pageSize' => 25,
            'personFields' => 'names,nicknames,emailAddresses,phoneNumbers,addresses,urls,organizations,birthdays,events,biographies,relations,photos,memberships,metadata',
            'sources[]' => 'READ_SOURCE_TYPE_CONTACT',
        ];
        if ($full) {
            $query['requestSyncToken'] = 'true';
        } else {
            $query['syncToken'] = $cursor->value;
        }
        if (is_string($pageToken) && $pageToken !== '') {
            $query['pageToken'] = $pageToken;
        }

        $response = $this->client->get($source, 'people/me/connections', $query);
        if ($response->status() === 400 && str_contains($response->body(), 'EXPIRED_SYNC_TOKEN')) {
            return new ContactSyncResultData([], [], new ContactSyncCursorData(null, 'google-people'), true);
        }
        $body = $response->throw()->json();
        if (! is_array($body)) {
            throw new RuntimeException('Invalid Google People response.');
        }

        $groups = $this->client->groups($source);
        $contacts = [];
        $deleted = [];
        foreach ($body['connections'] ?? [] as $person) {
            $remoteId = $person['resourceName'] ?? null;
            if (! is_string($remoteId) || ! str_starts_with($remoteId, 'people/')) {
                continue;
            }
            if (($person['metadata']['deleted'] ?? false) === true) {
                $deleted[] = $remoteId;

                continue;
            }
            $contacts[] = $this->mapPerson($source, $person, $groups);
        }

        $nextPage = $body['nextPageToken'] ?? null;
        $hasMore = is_string($nextPage) && $nextPage !== '';
        $nextToken = $hasMore ? $cursor?->value : ($body['nextSyncToken'] ?? null);
        if (! $hasMore && (! is_string($nextToken) || $nextToken === '')) {
            throw new RuntimeException('Google People did not return a sync token.');
        }

        return new ContactSyncResultData(
            $contacts,
            $deleted,
            new ContactSyncCursorData($nextToken, 'google-people', $hasMore
                ? ['page_token' => $nextPage, 'full' => $full, 'run_id' => $runId]
                : []),
            false,
            $hasMore,
            $runId,
            $full && ! $hasMore,
        );
    }

    /** @param array<string, mixed> $person
     * @param  array<string, string>  $groups
     */
    private function mapPerson(ContactSource $source, array $person, array $groups): ExternalContactData
    {
        $name = $person['names'][0] ?? [];
        $emails = $this->values($person['emailAddresses'] ?? [], 'value');
        $phones = $this->values($person['phoneNumbers'] ?? [], 'value');
        $addresses = $this->values($person['addresses'] ?? [], 'formattedValue');
        $urls = $this->values($person['urls'] ?? [], 'value');
        $displayName = trim((string) ($name['displayName'] ?? ''));
        $displayName = $displayName ?: ($emails[0]->value ?? $phones[0]->value ?? 'Sin nombre');
        $organization = $person['organizations'][0] ?? [];
        $birthday = $person['birthdays'][0]['date'] ?? [];
        $birthdayDate = isset($birthday['year'], $birthday['month'], $birthday['day'])
            ? sprintf('%04d-%02d-%02d', $birthday['year'], $birthday['month'], $birthday['day']) : null;
        $dates = [];
        if ($birthdayDate === null && isset($birthday['month'], $birthday['day'])) {
            $dates[] = ['kind' => 'birthday', 'label' => null, 'value' => sprintf('%02d-%02d', $birthday['month'], $birthday['day']), 'value_type' => 'text'];
        }
        foreach ($person['events'] ?? [] as $event) {
            $date = $event['date'] ?? [];
            if (! isset($date['month'], $date['day'])) {
                continue;
            }
            $value = isset($date['year'])
                ? sprintf('%04d-%02d-%02d', $date['year'], $date['month'], $date['day'])
                : sprintf('%02d-%02d', $date['month'], $date['day']);
            $dates[] = [
                'kind' => ($event['type'] ?? '') === 'anniversary' ? 'anniversary' : 'other',
                'label' => $event['formattedType'] ?? $event['type'] ?? null,
                'value' => $value,
                'value_type' => isset($date['year']) ? 'date' : 'text',
            ];
        }

        $categories = [];
        foreach ($person['memberships'] ?? [] as $membership) {
            $group = $membership['contactGroupMembership']['contactGroupResourceName'] ?? null;
            if ($group === 'contactGroups/starred') {
                $categories[] = $source->favoriteLabel();
            } elseif (is_string($group) && $group !== 'contactGroups/myContacts' && isset($groups[$group])) {
                $categories[] = $groups[$group];
            }
        }
        $relations = [];
        foreach ($person['relations'] ?? [] as $relation) {
            if (isset($relation['person'])) {
                $relations[] = ['type' => $relation['type'] ?? 'other', 'name' => $relation['person'], 'external_value' => null];
            }
        }
        $photoUrl = null;
        foreach ($person['photos'] ?? [] as $photo) {
            if (($photo['default'] ?? false) === false && is_string($photo['url'] ?? null)) {
                $photoUrl = $photo['url'];
                break;
            }
        }

        return new ExternalContactData(
            remoteId: $person['resourceName'],
            formattedName: $displayName,
            givenName: $name['givenName'] ?? null,
            familyName: $name['familyName'] ?? null,
            organization: $organization['name'] ?? null,
            jobTitle: $organization['title'] ?? null,
            birthday: $birthdayDate,
            notes: $person['biographies'][0]['value'] ?? null,
            emails: $emails,
            phones: $phones,
            addresses: $addresses,
            urls: $urls,
            photoBytes: is_string($photoUrl) ? $this->client->photo($photoUrl) : null,
            etag: $person['etag'] ?? null,
            href: $person['resourceName'],
            uid: $person['resourceName'],
            additionalName: $name['middleName'] ?? null,
            nickname: $person['nicknames'][0]['value'] ?? null,
            dates: $dates,
            relations: $relations,
            categories: array_values(array_unique($categories)),
        );
    }

    /** @param list<array<string, mixed>> $items
     * @return list<ContactValueData>
     */
    private function values(array $items, string $field): array
    {
        $values = [];
        foreach ($items as $item) {
            $value = $item[$field] ?? null;
            if (is_string($value) && trim($value) !== '') {
                $values[] = new ContactValueData($value, $item['type'] ?? null, ($item['metadata']['primary'] ?? false) === true);
            }
        }

        return $values;
    }
}
