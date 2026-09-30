<?php

namespace App\Actions\Contacts;

use App\Data\Contacts\ContactSourceData;
use App\Data\Contacts\ExternalCollectionData;
use App\Models\ContactSource;
use App\Models\User;
use App\Services\Contacts\ContactProviderRegistry;
use App\Services\Contacts\SafeRemoteConnectionClient;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class SaveContactSource
{
    public function __construct(
        private SafeRemoteConnectionClient $client,
        private ContactProviderRegistry $providers,
    ) {}

    public function requiresSync(ContactSource $source, ContactSourceData $data): bool
    {
        $currentCollections = $source->collections()->where('enabled', true)->pluck('remote_id')->filter()->sort()->values()->all();
        $requestedCollections = $data->selectedCollections;
        sort($requestedCollections);
        $credentials = $source->encrypted_credentials ?? [];

        return ($source->provider_configuration['server_url'] ?? null) !== $data->serverUrl
            || ($data->username !== null && $data->username !== ($credentials['username'] ?? null))
            || ($data->appPassword !== null && $data->appPassword !== ($credentials['password'] ?? null))
            || $source->enabled !== $data->enabled
            || $source->sync_enabled !== $data->syncEnabled
            || $currentCollections !== $requestedCollections;
    }

    public function execute(ContactSourceData $data, User $user, ?ContactSource $source = null): ContactSource
    {
        if ($source !== null && $source->household_id !== $data->householdId) {
            throw ValidationException::withMessages(['household_id' => 'Contact source ownership cannot be changed.']);
        }

        $existingCredentials = $source?->encrypted_credentials ?? [];
        $username = $data->username ?? $existingCredentials['username'] ?? null;
        $password = $data->appPassword ?? $existingCredentials['password'] ?? null;

        if ($username === null || $password === null) {
            throw ValidationException::withMessages([
                $username === null ? 'username' : 'app_password' => 'Las credenciales de la fuente son obligatorias.',
            ]);
        }

        $credentials = ['username' => $username, 'password' => $password];
        $favoriteLabel = $data->favoriteLabel ?? $source?->favoriteLabel() ?? ContactSource::DEFAULT_FAVORITE_LABEL;
        $selected = array_fill_keys($data->selectedCollections, true);
        $knownCollections = $source === null ? collect() : $source->collections()->get()->keyBy('remote_id');
        $connectionUnchanged = $source !== null
            && ($source->provider_configuration['server_url'] ?? null) === $data->serverUrl
            && $existingCredentials === $credentials;
        $useKnownCollections = $connectionUnchanged && $selected !== []
            && array_diff_key($selected, $knownCollections->all()) === [];

        if ($useKnownCollections) {
            $available = [];
            foreach ($knownCollections as $collection) {
                $available[$collection->remote_id] = new ExternalCollectionData(
                    $collection->remote_id,
                    $collection->name,
                    $collection->remote_href,
                    $collection->read_only,
                );
            }
        } else {
            try {
                $this->client->assertSafeUrl($data->serverUrl);
            } catch (\RuntimeException $exception) {
                throw ValidationException::withMessages(['server_url' => $exception->getMessage()]);
            }

            $candidate = new ContactSource([
                'provider' => 'carddav',
                'provider_configuration' => ['server_url' => $data->serverUrl],
                'encrypted_credentials' => $credentials,
            ]);

            try {
                $remoteCollections = $this->providers->for($candidate)->collections($candidate);
            } catch (\Exception $exception) {
                report($exception);

                throw ValidationException::withMessages(['selected_collections' => 'No se pudieron comprobar las libretas CardDAV. Revisa la conexión.']);
            }

            $available = [];
            foreach ($remoteCollections as $remote) {
                $available[$remote->remoteId] = $remote;
            }
        }

        if ($selected === [] || array_diff_key($selected, $available) !== []) {
            throw ValidationException::withMessages(['selected_collections' => 'Selecciona al menos una libreta descubierta en esta conexión.']);
        }

        return DB::transaction(function () use ($source, $data, $user, $credentials, $favoriteLabel, $available, $selected): ContactSource {
            if ($source === null) {
                $source = ContactSource::create([
                    'user_id' => $data->householdId === null ? $user->id : null,
                    'household_id' => $data->householdId,
                    'provider' => 'carddav',
                    'name' => $data->name,
                    'authentication_type' => 'app_password',
                    'encrypted_credentials' => $credentials,
                    'provider_configuration' => ['server_url' => $data->serverUrl, 'favorite_label' => $favoriteLabel],
                    'enabled' => $data->enabled,
                    'sync_enabled' => $data->syncEnabled,
                ]);
            } else {
                $source->update([
                    'name' => $data->name,
                    'encrypted_credentials' => $credentials,
                    'provider_configuration' => ['server_url' => $data->serverUrl, 'favorite_label' => $favoriteLabel],
                    'enabled' => $data->enabled,
                    'sync_enabled' => $data->syncEnabled,
                ]);
            }

            $source->collections()->whereNotIn('remote_id', array_keys($selected))->update(['enabled' => false]);

            foreach ($available as $remoteId => $remote) {
                $source->collections()->updateOrCreate(
                    ['remote_id' => $remoteId],
                    [
                        'remote_href' => $remote->href,
                        'name' => $remote->name,
                        'read_only' => $remote->readOnly,
                        'enabled' => isset($selected[$remoteId]),
                    ],
                );
            }

            return $source->load('collections');
        });
    }
}
