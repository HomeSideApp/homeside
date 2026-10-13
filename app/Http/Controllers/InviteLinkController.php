<?php

namespace App\Http\Controllers;

use App\Actions\Households\RegisterViaInviteLink;
use App\Data\Households\RegisterViaInviteLinkData;
use App\Http\Requests\RegisterViaInviteLinkRequest;
use App\Http\Requests\ViewInviteLinkRequest;
use App\Models\HouseholdInviteLink;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Handles the public registration page for reusable household invite links.
 */
class InviteLinkController extends Controller
{
    /**
     * Show the registration page for an invite link.
     *
     * @param  string  $token  The public invite link token.
     * @return Response The HTTP response.
     */
    public function show(ViewInviteLinkRequest $request, string $token): Response
    {
        $link = $request->inviteLink();

        $household = $link->household()->firstOrFail();

        return Inertia::render('auth/SetPassword', [
            'mode' => 'link',
            'token' => $link->token,
            'email' => '',
            'name' => '',
            'householdName' => $household->name,
            'storeUrl' => route('invite-links.store', $link->token),
        ]);
    }

    /**
     * Register a new verified user through an invite link.
     *
     * @param  RegisterViaInviteLinkRequest  $request  The incoming HTTP request.
     * @param  string  $token  The public invite link token.
     * @param  RegisterViaInviteLink  $action  The action responsible for the operation.
     * @return RedirectResponse The HTTP response.
     */
    public function store(RegisterViaInviteLinkRequest $request, string $token, RegisterViaInviteLink $action): RedirectResponse
    {
        $link = HouseholdInviteLink::query()->where('token', $token)->firstOrFail();

        $outcome = $action->execute(RegisterViaInviteLinkData::fromArray($request->validated()), $link, 'web');

        // An existing account is never taken over by an invitation: the owner
        // must authenticate as it.
        if (! $outcome->registered() || $outcome->user === null) {
            throw ValidationException::withMessages([
                'email' => __('app.errors.registration_identity_exists'),
            ]);
        }

        return redirect()->temporarySignedRoute('otp.setup', now()->addMinutes(30), ['user' => $outcome->user->id]);
    }
}
