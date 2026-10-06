<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Contacts\DiscoverContactCollections;
use App\Actions\Contacts\SaveContactSource;
use App\Actions\Contacts\SetContactCollectionEnabled;
use App\Data\Contacts\ContactSourceData;
use App\Http\Controllers\Controller;
use App\Http\Requests\DeleteContactSourceRequest;
use App\Http\Requests\IndexContactSourcesRequest;
use App\Http\Requests\SaveContactSourceRequest;
use App\Http\Requests\SyncContactCollectionRequest;
use App\Http\Requests\SyncContactSourceRequest;
use App\Http\Requests\UpdateContactCollectionRequest;
use App\Http\Requests\ViewContactSourceRequest;
use App\Http\Resources\Contacts\ContactCollectionResource;
use App\Http\Resources\Contacts\ContactSourceResource;
use App\Jobs\SyncContactCollectionJob;
use App\Jobs\SyncContactSourceJob;
use App\Models\ContactCollection;
use App\Models\ContactSource;
use App\Services\Contacts\ContactProviderRegistry;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class ContactSourceController extends Controller
{
    public function index(IndexContactSourcesRequest $request): AnonymousResourceCollection
    {

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

    public function show(ViewContactSourceRequest $request, ContactSource $source): ContactSourceResource
    {

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

    public function destroy(DeleteContactSourceRequest $request, ContactSource $source): Response
    {
        $source->delete();

        return response()->noContent();
    }

    public function test(SyncContactSourceRequest $request, ContactSource $source, ContactProviderRegistry $registry): Response
    {
        $registry->for($source)->collections($source);

        return response()->noContent();
    }

    public function discover(SyncContactSourceRequest $request, ContactSource $source, DiscoverContactCollections $discover): AnonymousResourceCollection
    {

        return ContactCollectionResource::collection($discover->execute($source));
    }

    public function collections(ViewContactSourceRequest $request, ContactSource $source): AnonymousResourceCollection
    {

        return ContactCollectionResource::collection($source->collections()->orderBy('name')->get());
    }

    public function sync(SyncContactSourceRequest $request, ContactSource $source): Response
    {
        SyncContactSourceJob::dispatch($source->id);

        return response()->noContent(202);
    }

    public function syncCollection(SyncContactCollectionRequest $request, ContactCollection $collection): Response
    {
        SyncContactCollectionJob::dispatch($collection->id);

        return response()->noContent(202);
    }

    public function updateCollection(UpdateContactCollectionRequest $request, ContactCollection $collection, SetContactCollectionEnabled $setEnabled): ContactCollectionResource
    {
        $data = $request->validated();
        $setEnabled->execute($collection, $data['enabled']);

        return new ContactCollectionResource($collection);
    }
}
