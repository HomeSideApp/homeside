<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class EmailVerificationController extends Controller
{
    /**
     * Show the email verification page.
     */
    public function show(Request $request): Response|RedirectResponse
    {
        if ($this->authenticatedUser($request)->hasVerifiedEmail()) {
            return redirect()->intended(route('dashboard'));
        }

        return Inertia::render('auth/VerifyEmail', [
            'status' => $request->session()->get('status'),
        ]);
    }

    /**
     * Send a new email verification notification.
     */
    public function store(Request $request): RedirectResponse
    {
        $user = $this->authenticatedUser($request);
        if ($user->hasVerifiedEmail()) {
            return redirect()->intended('/dashboard');
        }

        $user->sendEmailVerificationNotification();

        return back()->with('status', 'verification-link-sent');
    }

    /**
     * Mark the authenticated user's email address as verified.
     */
    public function verify(Request $request, string $id, string $hash): RedirectResponse
    {
        $user = $this->authenticatedUser($request);

        if (! hash_equals((string) $request->route('id'), (string) $user->getKey())) {
            abort(403);
        }

        if (! hash_equals((string) $hash, sha1($user->getEmailForVerification()))) {
            abort(403);
        }

        if ($user->hasVerifiedEmail()) {
            return redirect()->intended(route('dashboard').'?verified=1');
        }

        if ($user->markEmailAsVerified()) {
            event(new Verified($user));
        }

        return redirect()->intended(route('dashboard').'?verified=1');
    }
}
