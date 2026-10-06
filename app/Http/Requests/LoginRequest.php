<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Auth;

class LoginRequest extends FormRequest
{
    public function validateAuthenticatedAccount(User $user): void
    {
        if (! $user->canAccessApplication()) {
            Auth::logout();
            throw new HttpResponseException(back()->withErrors(['email' => __('app.errors.account_inactive')])->onlyInput('email'));
        }

        if (! $user->hasEnabledTwoFactorAuthentication()) {
            Auth::logout();
            throw new HttpResponseException(back()->withErrors(['email' => __('app.auth.account_without_otp')])->onlyInput('email'));
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ];
    }
}
