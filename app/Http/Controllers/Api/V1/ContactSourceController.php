<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Contacts\DiscoverContactCollections;
use App\Actions\Contacts\SaveContactSource;
use App\Actions\Contacts\SetContactCollectionEnabled;
use App\Data\Contacts\ContactSourceData;
use App\Http\Controllers\Controller;
use App\Http\Requests\SaveContactSourceRequest;
use App\Http\Resources\Contacts\ContactCollectionResource;
use App\Http\Resources\Contacts\ContactSourceResource;
use App\Jobs\SyncContactCollectionJob;
use App\Jobs\SyncContactSourceJob;
use App\Models\ContactCollection;
use App\Models\ContactSource;
use App\Services\Contacts\ContactProviderRegistry;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class ContactSourceController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', ContactSource::class);

        return ContactSourceResource::collection(ContactSource::query()->visibleTo($this->authenticatedUser($request))->with('collections')->orderBy('name')->get());
    }

    public function store(SaveContactSourceRequest $request, SaveContactSource $action): ContactSourceResource
    {
        $source = $action->execute(ContactSourceData::fromArray($request->validated()), $this->authenticatedUser($request));
        if ($source->enabled && $source->sync_enabled) {
            SyncContactSourceJob::dispatch($source->id);
        }

        return new ContactSourceResource($source);
    }

    public function show(ContactSource $source): ContactSourceResource
    {
        Gate::authorize('view', $source);

        return new ContactSourceResource($source->load('collections'));
    }

    public function update(SaveContactSourceRequest $request, ContactSource $source, SaveContactSource $action): ContactSourceResource
    {
        $data = $request->validated() + [
            'enabled' => $source->enabled,
            'sync_enabled' => $source->sync_enabled,
        ];

        $sourceData = ContactSourceData::fromArray($data, $source->household_id);
        $requiresSync = $action->requiresSync($source, $sourceData);
        $source = $action->execute($sourceData, $this->authenticatedUser($request), $source);
        if ($source->enabled && $source->sync_enabled && $requiresSync) {
            SyncContactSourceJob::dispatch($source->id);
        }

        return new ContactSourceResource($source);
    }

    public function destroy(ContactSource $source): Response
    {
        Gate::authorize('delete', $source);
        $source->delete();

        return response()->noContent();
    }

    public function test(ContactSource $source, ContactProviderRegistry $registry): Response
    {
        Gate::authorize('sync', $source);
        $registry->for($source)->collections($source);

        return response()->noContent();
    }

    public function discover(ContactSource $source, DiscoverContactCollections $discover): AnonymousResourceCollection
    {
        Gate::authorize('sync', $source);

        return ContactCollectionResource::collection($discover->execute($source));
    }

    public function collections(ContactSource $source): AnonymousResourceCollection
    {
        Gate::authorize('view', $source);

        return ContactCollectionResource::collection($source->collections()->orderBy('name')->get());
    }

    public function sync(ContactSource $source): Response
    {
        Gate::authorize('sync', $source);
        SyncContactSourceJob::dispatch($source->id);

        return response()->noContent(202);
    }

    public function syncCollection(ContactCollection $collection): Response
    {
        Gate::authorize('sync', $collection);
        SyncContactCollectionJob::dispatch($collection->id);

        return response()->noContent(202);
    }

    public function updateCollection(Request $request, ContactCollection $collection, SetContactCollectionEnabled $setEnabled): ContactCollectionResource
    {
        Gate::authorize('update', $collection);
        $data = $request->validate(['enabled' => ['required', 'boolean']]);
        $setEnabled->execute($collection, $data['enabled']);

        return new ContactCollectionResource($collection);
    }
}
