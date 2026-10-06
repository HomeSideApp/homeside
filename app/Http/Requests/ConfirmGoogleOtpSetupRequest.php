<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\ValidationException;
use PragmaRX\Google2FA\Google2FA;

class ConfirmGoogleOtpSetupRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->setupUser();
        if (! $user->canAccessApplication() || ! is_string($this->session()->get('google.otp_secret'))) {
            throw new HttpResponseException(to_route('login'));
        }

        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['code' => ['required', 'digits:6']];
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

    protected function passedValidation(): void
    {
        if (! (new Google2FA)->verifyKey($this->session()->get('google.otp_secret'), $this->validated('code'))) {
            throw ValidationException::withMessages(['code' => __('app.errors.otp_code_invalid_short')]);
        }
    }
}
