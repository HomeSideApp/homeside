<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

class AcceptInvitationRequest extends FormRequest
{
    public function invitedUser(): User
    {
        $user = User::query()->whereKey($this->validated('token'))->whereNull('email_verified_at')->first();
        abort_unless($user !== null, 404, __('app.errors.invitation_invalid'));

        return $user;
    }

    protected function passedValidation(): void
    {
        $this->invitedUser();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'token' => ['required', 'string'],
            'name' => ['nullable', 'string', 'max:255'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ];
    }
}
