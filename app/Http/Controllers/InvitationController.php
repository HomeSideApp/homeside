<?php

namespace App\Http\Controllers;

use App\Actions\Auth\RegisterAccount;
use App\Data\Auth\InvitationContextData;
use App\Data\Auth\RegisterAccountData;
use App\Enums\AppLocale;
use App\Enums\RegistrationChannel;
use App\Http\Requests\AcceptInvitationRequest;
use App\Http\Requests\ViewInvitationRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Handles user invitations and password set-up for invited users.
 */
class InvitationController
{
    /**
     * Show the set-password page for a pending invitation.
     *
     * @param  User  $user  The authenticated user.
     * @return Response The HTTP response.
     */
    public function show(ViewInvitationRequest $request, User $user): Response
    {
        return Inertia::render('auth/SetPassword', [
            'token' => $user->getRouteKey(),
            'email' => $user->email,
            'name' => $user->name,
            'storeUrl' => URL::temporarySignedRoute('invitation.store', now()->addMinutes(30)),
        ]);
    }

    /**
     * Complete an invited user's account.
     *
     * The account was already created by an administrator, so this never
     * re-creates it: it routes through the shared RegisterAccount use case with
     * a targeted invitation context, which completes the pre-created row,
     * proves possession of the address (the signed link was mailed to it) and
     * leaves the roles the administrator chose explicitly untouched.
     *
     * @param  AcceptInvitationRequest  $request  The incoming HTTP request.
     * @return RedirectResponse The HTTP response.
     */
    public function store(AcceptInvitationRequest $request, RegisterAccount $accounts): RedirectResponse
    {
        $validated = $request->validated();
        $user = $request->invitedUser();

        $outcome = $accounts->execute(new RegisterAccountData(
            name: $validated['name'] ?? null,
            email: $user->email,
            password: $validated['password'],
            locale: $user->locale ?? AppLocale::default()->value,
            channel: RegistrationChannel::AdminInvitation,
            invitation: new InvitationContextData(
                reference: $user->getRouteKey(),
                targetUserId: $user->getRouteKey(),
                emailVerified: true,
                targeted: true,
            ),
            origin: 'web',
        ));

        if (! $outcome->registered() || $outcome->user === null) {
            throw ValidationException::withMessages([
                'password' => __('app.errors.registration_identity_exists'),
            ]);
        }

        return redirect()->temporarySignedRoute('otp.setup', now()->addMinutes(30), ['user' => $outcome->user->id]);
    }
}
