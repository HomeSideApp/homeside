<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use PragmaRX\Google2FA\Google2FA;

class TwoFactorChallengeRequest extends FormRequest
{
    private ?User $loginUser = null;

    /** @var list<string>|null */
    private ?array $remainingRecoveryCodes = null;

    public function authorize(): bool
    {
        $loginId = $this->session()->get('login.id');
        $this->loginUser = $loginId ? User::query()->whereKey($loginId)->first() : null;
        if ($this->loginUser === null || ! $this->loginUser->canAccessApplication()) {
            $this->session()->forget('login.id');
            throw new HttpResponseException(to_route('login'));
        }

        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'code' => ['nullable', 'string'],
            'recovery_code' => ['nullable', 'string'],
            'remember' => ['sometimes', 'boolean'],
        ];
    }

    protected function passedValidation(): void
    {
        $user = $this->loginUser();
        if ($this->filled('code')) {
            if ($user->two_factor_secret === null) {
                throw new HttpResponseException(to_route('login'));
            }
            if (! (new Google2FA)->verifyKey(decrypt($user->two_factor_secret), $this->validated('code'))) {
                throw new HttpResponseException(back()->withErrors(['code' => __('app.auth.two_factor_code_invalid')]));
            }

            return;
        }

        if ($this->filled('recovery_code')) {
            $codes = $user->two_factor_recovery_codes === null ? null : json_decode(decrypt($user->two_factor_recovery_codes), true);
            if (! is_array($codes)) {
                throw new HttpResponseException(to_route('login'));
            }
            if (! in_array($this->validated('recovery_code'), $codes)) {
                throw new HttpResponseException(back()->withErrors(['recovery_code' => __('app.auth.two_factor_recovery_code_invalid')]));
            }
            $this->remainingRecoveryCodes = array_values(array_diff($codes, [$this->validated('recovery_code')]));

            return;
        }

        throw new HttpResponseException(back()->withErrors(['code' => __('app.auth.two_factor_code_required')]));
    }

    public function loginUser(): User
    {
        return $this->loginUser ?? throw new \LogicException('The login challenge has not been authorized.');
    }

    /** @return list<string>|null */
    public function remainingRecoveryCodes(): ?array
    {
        return $this->remainingRecoveryCodes;
    }
}
