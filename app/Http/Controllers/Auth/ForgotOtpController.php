<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\ForgotOtpRequest;
use App\Models\User;
use App\Notifications\ResetOtpNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\URL;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Handles the forgot OTP request flow.
 */
class ForgotOtpController extends Controller
{
    /**
     * Show the forgot OTP page.
     *
     * @return Response The HTTP response.
     */
    public function show(): Response
    {
        return Inertia::render('auth/ForgotOtp', [
            'status' => session('status'),
        ]);
    }

    /**
     * Send a reset OTP link with signed URL to the given user.
     *
     * @param  ForgotOtpRequest  $request  The incoming HTTP request.
     * @return RedirectResponse The HTTP response.
     */
    public function store(ForgotOtpRequest $request): RedirectResponse
    {
        $user = User::where('email', $request->input('email'))
            ->first();

        if (! $user) {
            return back()->with('status', __('app.auth.otp_reset_link_sent'));
        }

        $resetUrl = URL::temporarySignedRoute(
            'otp.reset',
            now()->addMinutes(30),
            ['user' => $user->id]
        );

        $user->notify(new ResetOtpNotification($user, $resetUrl));

        return back()->with('status', __('app.auth.otp_reset_link_sent'));
    }
}
