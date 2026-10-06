<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

class SetupOtpResetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'email' => ['required', 'email'],
            'token' => ['required', 'string'],
        ];
    }

    public function resetUser(): User
    {
        $user = User::query()->where('email', Str::lower($this->validated('email')))->first();
        abort_unless($user !== null && $user->canAccessApplication(), 403, __('app.errors.account_not_approved'));

        return $user;
    }

    protected function passedValidation(): void
    {
        abort_unless(Password::broker()->tokenExists($this->resetUser(), $this->validated('token')), 422, __('app.errors.reset_token_invalid'));
    }
}
