<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\ResetPasswordRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\URL;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Handles the password reset completion flow.
 */
class ResetPasswordController extends Controller
{
    /**
     * Show the reset password page (with signed URL).
     *
     * @param  User  $user  The authenticated user.
     * @return Response|RedirectResponse The HTTP response.
     */
    public function show(User $user): Response|RedirectResponse
    {
        return Inertia::render('auth/ResetPassword', [
            'user' => $user->id,
            'storeUrl' => URL::temporarySignedRoute(
                'password.update',
                now()->addMinutes(30),
                ['user' => $user->id]
            ),
        ]);
    }

    /**
     * Reset the user's password (with signed URL).
     *
     * @param  ResetPasswordRequest  $request  The incoming HTTP request.
     * @param  User  $user  The authenticated user.
     * @return RedirectResponse The HTTP response.
     */
    public function store(ResetPasswordRequest $request, User $user): RedirectResponse
    {
        $validated = $request->validated();

        $user->update([
            'password' => Hash::make($validated['password']),
        ]);

        // Revocar tokens de acceso API (Sanctum) y sesiones web del usuario
        $user->tokens()->delete();
        DB::table('sessions')->where('user_id', $user->id)->delete();

        return redirect()->route('login')->with('status', __('app.auth.password_reset'));
    }
}
