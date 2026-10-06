<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Cache;
use PragmaRX\Google2FA\Google2FA;

class ConfirmTwoFactorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['challenge_id' => ['required', 'uuid'], 'code' => ['required', 'digits:6']];
    }

    /** @return array<string, mixed> */
    public function challenge(): array
    {
        $user = $this->user();
        abort_unless($user !== null, 403);
        $challenge = Cache::get('two-factor-challenge:'.$this->validated('challenge_id'));
        abort_unless(is_array($challenge) && hash_equals($user->id, (string) ($challenge['user_id'] ?? '')), 422, __('app.errors.challenge_invalid'));

        return $challenge;
    }

    protected function passedValidation(): void
    {
        $challenge = $this->challenge();
        $expectedType = $this->routeIs('*.reset.confirm') ? 'reset' : 'setup';
        abort_unless(($challenge['type'] ?? null) === $expectedType, 422, __('app.errors.challenge_wrong_operation'));
        abort_unless((bool) (new Google2FA)->verifyKey($challenge['secret'], $this->validated('code')), 422, __('app.errors.otp_code_invalid_short'));
    }
}
