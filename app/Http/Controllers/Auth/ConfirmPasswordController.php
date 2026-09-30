<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\ConfirmPasswordRequest;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Handles password confirmation for sensitive actions.
 */
class ConfirmPasswordController extends Controller
{
    /**
     * Show the confirm password page.
     *
     * @return Response The HTTP response.
     */
    public function show(): Response
    {
        return Inertia::render('auth/ConfirmPassword');
    }

    /**
     * Confirm the user's password.
     *
     * @param  ConfirmPasswordRequest  $request  The incoming HTTP request.
     * @return RedirectResponse The HTTP response.
     */
    public function store(ConfirmPasswordRequest $request): RedirectResponse
    {
        $request->validated();

        $request->session()->put('auth.password_confirmed_at', time());

        return back();
    }
}
