<?php

namespace App\Http\Controllers\Web;

use App\Actions\Contacts\FindContactDuplicates;
use App\Actions\Contacts\MergeContacts;
use App\Http\Controllers\Controller;
use App\Models\Contact;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ContactDuplicateController extends Controller
{
    public function index(Request $request, FindContactDuplicates $duplicates): Response
    {
        Gate::authorize('viewAny', Contact::class);

        return Inertia::render('contacts/Duplicates', [
            'groups' => $duplicates->execute($this->authenticatedUser($request)),
        ]);
    }

    public function store(Request $request, MergeContacts $merge): RedirectResponse
    {
        Gate::authorize('viewAny', Contact::class);
        $validated = $request->validate([
            'groups' => ['required', 'array', 'min:1', 'max:100'],
            'groups.*.primary_id' => ['required', 'uuid'],
            'groups.*.duplicate_ids' => ['required', 'array', 'min:1', 'max:100'],
            'groups.*.duplicate_ids.*' => ['required', 'uuid', 'distinct'],
        ]);
        $user = $this->authenticatedUser($request);

        foreach ($validated['groups'] as $group) {
            foreach ([$group['primary_id'], ...$group['duplicate_ids']] as $id) {
                $contact = Contact::query()->visibleTo($user)->findOrFail($id);
                Gate::authorize('update', $contact);
            }
        }

        $merge->execute($user, $validated['groups']);

        return redirect()->route('contacts.duplicates.index');
    }
}
