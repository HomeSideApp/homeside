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
use Illuminate\Support\Facades\DB;
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
        $user = $this->authenticatedUser($request);

        return Inertia::render('settings/Profile', [
            'mustVerifyEmail' => $request->user() instanceof MustVerifyEmail,
            'googleConnected' => $user->googleIdentity()->exists(),
            // Permiso para que el análisis de tickets use un proveedor de IA que
            // no corre en este servidor. Se revoca desde el mismo interruptor.
            'allowCloudAi' => $user->ai_receipt_consent_at !== null,
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

        // Un form HTML manda "1"/"0" y la API un booleano: `boolean()` entiende
        // los dos, así el interruptor y el JSON se comportan igual.
        $consent = $request->has('allow_cloud_ai') ? $request->boolean('allow_cloud_ai') : null;
        $user->fill($request->safe()->except('allow_cloud_ai'));

        if ($consent !== null) {
            $user->ai_receipt_consent_at = $consent ? ($user->ai_receipt_consent_at ?? now()) : null;
        }

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

        DB::transaction(fn () => $user->delete());

        Auth::logoutCurrentDevice();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}
