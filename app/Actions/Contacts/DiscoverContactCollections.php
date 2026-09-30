<?php

namespace App\Actions\Contacts;

use App\Models\ContactCollection;
use App\Models\ContactSource;
use App\Services\Contacts\ContactProviderRegistry;
use Illuminate\Database\Eloquent\Collection;

final class DiscoverContactCollections
{
    public function __construct(private ContactProviderRegistry $registry) {}

    /** @return Collection<int, ContactCollection> */
    public function execute(ContactSource $source): Collection
    {
        foreach ($this->registry->for($source)->collections($source) as $remote) {
            $collection = $source->collections()->firstOrNew(['remote_id' => $remote->remoteId]);
            if (! $collection->exists) {
                $collection->enabled = false;
            }
            $collection->fill([
                'remote_href' => $remote->href,
                'name' => $remote->name,
                'read_only' => $remote->readOnly,
            ]);
            $collection->save();
        }

        return $source->collections()->orderBy('name')->get();
    }
}
