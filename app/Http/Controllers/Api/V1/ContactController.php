<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Contacts\SaveLocalContact;
use App\Data\Contacts\ContactData;
use App\Http\Controllers\Controller;
use App\Http\Requests\DeleteContactRequest;
use App\Http\Requests\IndexContactsRequest;
use App\Http\Requests\SaveContactRequest;
use App\Http\Requests\ViewContactRequest;
use App\Http\Resources\Contacts\ContactResource;
use App\Models\Contact;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class ContactController extends Controller
{
    public function index(IndexContactsRequest $request): AnonymousResourceCollection
    {

        return ContactResource::collection(Contact::query()
            ->visibleTo($this->authenticatedUser($request))
            ->orderBy('display_name')->orderBy('id')->paginate(30));
    }

    public function store(SaveContactRequest $request, SaveLocalContact $action): ContactResource
    {
        return new ContactResource($action->execute(ContactData::fromArray($request->validated()), $this->authenticatedUser($request), photo: $request->file('photo'), removePhoto: $request->boolean('remove_photo')));
    }

    public function show(Contact $contact, ViewContactRequest $request): ContactResource
    {

        return new ContactResource($contact->load('records'));
    }

    public function update(SaveContactRequest $request, Contact $contact, SaveLocalContact $action): ContactResource
    {
        return new ContactResource($action->execute(ContactData::fromArray($request->validated(), $contact->household_id), $this->authenticatedUser($request), $contact, $request->file('photo'), $request->boolean('remove_photo')));
    }

    public function destroy(Contact $contact, DeleteContactRequest $request): Response
    {
        $contact->delete();

        return response()->noContent();
    }
}
