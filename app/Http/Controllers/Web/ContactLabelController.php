<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\DeleteContactLabelRequest;
use App\Http\Requests\StoreContactLabelRequest;
use App\Models\ContactLabel;
use Illuminate\Http\RedirectResponse;

class ContactLabelController extends Controller
{
    public function store(StoreContactLabelRequest $request): RedirectResponse
    {
        $user = $this->authenticatedUser($request);
        $validated = $request->validated();

        ContactLabel::create(['user_id' => $user->id, 'name' => trim($validated['name'])]);

        return back();
    }

    public function destroy(DeleteContactLabelRequest $request, ContactLabel $label): RedirectResponse
    {
        $label->delete();

        return redirect()->route('contacts.index');
    }
}
