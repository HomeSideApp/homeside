<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\ProfileDeleteRequest;
use App\Http\Requests\Settings\ProfileUpdateRequest;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Handles the user's profile settings.
 */
class ProfileController extends Controller
{
    /**
     * Show the user's profile settings page.
     *
     * @param  Request  $request  The incoming HTTP request.
     * @return Response The HTTP response.
     */
    public function edit(Request $request): Response
    {
        return Inertia::render('settings/Profile', [
            'mustVerifyEmail' => $request->user() instanceof MustVerifyEmail,
            'googleConnected' => $request->user()->googleIdentity()->exists(),
            'status' => $request->session()->get('status'),
        ]);
    }

    /**
     * Update the user's profile information.
     *
     * @param  ProfileUpdateRequest  $request  The incoming HTTP request.
     * @return RedirectResponse The HTTP response.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $user = $this->authenticatedUser($request);
        $user->fill($request->validated());

        if (! $user->households_enabled) {
            $user->active_household_id = null;
        }

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        $user->save();

        App::setLocale($user->preferredLocale());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('app.profile.updated')]);

        return to_route('profile.edit');
    }

    /**
     * Delete the user's profile.
     *
     * @param  ProfileDeleteRequest  $request  The incoming HTTP request.
     * @return RedirectResponse The HTTP response.
     */
    public function destroy(ProfileDeleteRequest $request): RedirectResponse
    {
        $user = $this->authenticatedUser($request);

        Auth::logout();

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}
