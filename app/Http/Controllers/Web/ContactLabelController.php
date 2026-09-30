<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Contact;
use App\Models\ContactLabel;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class ContactLabelController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('create', Contact::class);
        $user = $this->authenticatedUser($request);
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:80', Rule::unique('contact_labels', 'name')->where('user_id', $user->id)],
        ]);

        ContactLabel::create(['user_id' => $user->id, 'name' => trim($validated['name'])]);

        return back();
    }

    public function destroy(Request $request, ContactLabel $label): RedirectResponse
    {
        Gate::authorize('create', Contact::class);
        abort_unless($label->user_id === $this->authenticatedUser($request)->id, 404);
        abort_if($label->contacts()->wherePivot('imported', true)->exists(), 403);
        $label->delete();

        return redirect()->route('contacts.index');
    }
}
