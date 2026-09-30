<?php

namespace App\Http\Controllers;

use App\Http\Requests\AcceptInvitationRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\URL;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Handles user invitations and password set-up for invited users.
 */
class InvitationController extends Controller
{
    /**
     * Show the set-password page for a pending invitation.
     *
     * @param  User  $user  The authenticated user.
     * @return Response The HTTP response.
     */
    public function show(User $user): Response
    {
        if ($user->email_verified_at !== null) {
            abort(404, __('app.errors.invitation_invalid'));
        }

        return Inertia::render('auth/SetPassword', [
            'token' => $user->getRouteKey(),
            'email' => $user->email,
            'name' => $user->name,
            'storeUrl' => URL::temporarySignedRoute('invitation.store', now()->addMinutes(30)),
        ]);
    }

    /**
     * Set the invited user's password, optional real name and verify their email.
     *
     * @param  AcceptInvitationRequest  $request  The incoming HTTP request.
     * @return RedirectResponse The HTTP response.
     */
    public function store(AcceptInvitationRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $user = User::where('id', $validated['token'])
            ->whereNull('email_verified_at')
            ->first();

        if (! $user) {
            abort(404, __('app.errors.invitation_invalid'));
        }

        $user->update(array_filter([
            'password' => Hash::make($validated['password']),
            'name' => $validated['name'] ?? null,
        ], fn (mixed $value): bool => $value !== null));

        $user->markEmailAsVerified();

        return redirect()->temporarySignedRoute('otp.setup', now()->addMinutes(30), ['user' => $user->id]);
    }
}
