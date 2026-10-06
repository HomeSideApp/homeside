<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

class ConfirmEmailRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['id' => ['required', 'uuid'], 'hash' => ['required', 'string']];
    }

    public function verificationUser(): User
    {
        return User::query()->whereKey($this->validated('id'))->firstOrFail();
    }

    protected function passedValidation(): void
    {
        abort_unless(hash_equals(sha1($this->verificationUser()->getEmailForVerification()), $this->validated('hash')), 403, __('app.errors.verification_token_invalid'));
    }
}
