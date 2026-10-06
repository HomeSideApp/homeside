<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class ShowGoogleOtpSetupRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->setupUser();
        if (! $user->canAccessApplication() || $user->hasEnabledTwoFactorAuthentication()) {
            throw new HttpResponseException(to_route('login'));
        }

        return true;
    }

    public function setupUser(): User
    {
        $userId = $this->session()->get('google.setup_id');
        $user = is_string($userId) ? User::query()->find($userId) : null;
        if ($user === null) {
            throw new HttpResponseException(to_route('login'));
        }

        return $user;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [];
    }
}
