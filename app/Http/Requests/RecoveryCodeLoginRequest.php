<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

class RecoveryCodeLoginRequest extends FormRequest
{
    /** @var list<string> */
    private array $remainingCodes = [];

    public function authorize(): bool
    {
        $user = $this->user();
        abort_unless($user?->currentAccessToken()?->can('otp-verify') === true, 401, __('app.errors.token_temporal_invalid'));
        abort_unless($user->canAccessApplication(), 403, __('app.errors.account_not_approved'));

        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['recovery_code' => ['required', 'string']];
    }

    protected function passedValidation(): void
    {
        $user = $this->user();
        abort_unless($user !== null, 401);
        /** @var list<string> $codes */
        $codes = $user->two_factor_recovery_codes === null
            ? [] : json_decode(decrypt($user->two_factor_recovery_codes), true, flags: JSON_THROW_ON_ERROR);
        $index = collect($codes)->search(fn (string $code): bool => hash_equals($code, Str::upper($this->validated('recovery_code'))));
        abort_if($index === false, 422, __('app.errors.recovery_code_invalid'));
        unset($codes[$index]);
        $this->remainingCodes = array_values($codes);
    }

    /** @return list<string> */
    public function remainingCodes(): array
    {
        return $this->remainingCodes;
    }
}
