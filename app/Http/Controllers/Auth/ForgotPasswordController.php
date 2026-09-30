<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\ForgotPasswordRequest;
use App\Models\User;
use App\Notifications\ResetPasswordNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\URL;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Handles the password reset request flow.
 */
class ForgotPasswordController extends Controller
{
    /**
     * Show the forgot password page.
     *
     * @return Response The HTTP response.
     */
    public function show(): Response
    {
        return Inertia::render('auth/ForgotPassword', [
            'status' => session('status'),
        ]);
    }

    /**
     * Send a password reset link with signed URL to the given user.
     *
     * @param  ForgotPasswordRequest  $request  The incoming HTTP request.
     * @return RedirectResponse The HTTP response.
     */
    public function store(ForgotPasswordRequest $request): RedirectResponse
    {
        $user = User::where('email', $request->input('email'))->first();

        if (! $user) {
            return back()->with('status', __('app.auth.password_reset_link_sent'));
        }

        $resetUrl = URL::temporarySignedRoute(
            'password.reset',
            now()->addMinutes(30),
            ['user' => $user->id]
        );

        // Enviar email con la URL firmada
        $user->notify(new ResetPasswordNotification($user, $resetUrl));

        return back()->with('status', __('app.auth.password_reset_link_sent'));
    }
}
