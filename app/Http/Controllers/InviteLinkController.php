<?php

namespace App\Http\Controllers;

use App\Actions\Households\RegisterViaInviteLink;
use App\Data\Households\RegisterViaInviteLinkData;
use App\Http\Requests\RegisterViaInviteLinkRequest;
use App\Models\HouseholdInviteLink;
use Illuminate\Http\RedirectResponse;
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
    public function show(string $token): Response
    {
        $link = HouseholdInviteLink::query()->where('token', $token)->firstOrFail();
        abort_unless($link->isUsable(), 404, __('app.errors.invite_link_unusable'));

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
        abort_unless($request->validated()['token'] === $token, 422, __('app.errors.invite_link_unusable'));

        $user = $action->execute(RegisterViaInviteLinkData::fromArray($request->validated()), $link);

        return redirect()->temporarySignedRoute('otp.setup', now()->addMinutes(30), ['user' => $user->id]);
    }
}
