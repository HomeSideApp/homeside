<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Contacts\SaveLocalContact;
use App\Data\Contacts\ContactData;
use App\Http\Controllers\Controller;
use App\Http\Requests\SaveContactRequest;
use App\Http\Resources\Contacts\ContactResource;
use App\Models\Contact;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class ContactController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Contact::class);

        return ContactResource::collection(Contact::query()
            ->visibleTo($this->authenticatedUser($request))
            ->orderBy('display_name')->orderBy('id')->paginate(30));
    }

    public function store(SaveContactRequest $request, SaveLocalContact $action): ContactResource
    {
        return new ContactResource($action->execute(ContactData::fromArray($request->validated()), $this->authenticatedUser($request), photo: $request->file('photo'), removePhoto: $request->boolean('remove_photo')));
    }

    public function show(Contact $contact): ContactResource
    {
        Gate::authorize('view', $contact);

        return new ContactResource($contact->load('records'));
    }

    public function update(SaveContactRequest $request, Contact $contact, SaveLocalContact $action): ContactResource
    {
        return new ContactResource($action->execute(ContactData::fromArray($request->validated(), $contact->household_id), $this->authenticatedUser($request), $contact, $request->file('photo'), $request->boolean('remove_photo')));
    }

    public function destroy(Contact $contact): Response
    {
        Gate::authorize('delete', $contact);
        $contact->delete();

        return response()->noContent();
    }
}
