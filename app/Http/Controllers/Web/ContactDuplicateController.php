<?php

namespace App\Http\Controllers\Web;

use App\Actions\Contacts\FindContactDuplicates;
use App\Actions\Contacts\MergeContacts;
use App\Http\Controllers\Controller;
use App\Http\Requests\IndexContactsRequest;
use App\Http\Requests\MergeContactDuplicatesRequest;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class ContactDuplicateController extends Controller
{
    public function index(IndexContactsRequest $request, FindContactDuplicates $duplicates): Response
    {

        return Inertia::render('contacts/Duplicates', [
            'groups' => $duplicates->execute($this->authenticatedUser($request)),
        ]);
    }

    public function store(MergeContactDuplicatesRequest $request, MergeContacts $merge): RedirectResponse
    {
        $validated = $request->validated();
        $user = $this->authenticatedUser($request);

        $merge->execute($user, $validated['groups']);

        return redirect()->route('contacts.duplicates.index');
    }
}
