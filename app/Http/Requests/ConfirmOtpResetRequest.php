<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Password;
use PragmaRX\Google2FA\Google2FA;

class ConfirmOtpResetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'challenge_id' => ['required', 'uuid'],
            'code' => ['required', 'digits:6'],
        ];
    }

    /** @return array<string, mixed> */
    public function challenge(): array
    {
        $challenge = cache()->get('public-otp-reset:'.$this->validated('challenge_id'));
        abort_unless(is_array($challenge), 422, __('app.errors.challenge_invalid'));

        return $challenge;
    }

    public function resetUser(): User
    {
        $user = User::query()->whereKey($this->challenge()['user_id'])->firstOrFail();
        abort_unless($user->canAccessApplication(), 403, __('app.errors.account_not_approved'));

        return $user;
    }

    protected function passedValidation(): void
    {
        $challenge = $this->challenge();
        abort_unless(Password::broker()->tokenExists($this->resetUser(), $challenge['token']), 422, __('app.errors.reset_token_invalid'));
        abort_unless((bool) (new Google2FA)->verifyKey($challenge['secret'], $this->validated('code')), 422, __('app.errors.otp_code_invalid_short'));
    }
}
