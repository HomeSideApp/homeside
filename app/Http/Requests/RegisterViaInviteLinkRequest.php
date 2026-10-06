<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RegisterViaInviteLinkRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function passedValidation(): void
    {
        abort_unless($this->validated('token') === $this->route('token'), 422, __('app.errors.invite_link_unusable'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'token' => ['required', 'string'],
            'name' => ['nullable', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ];
    }
}
