<?php

namespace App\Http\Requests;

use App\Models\HouseholdInvitation;
use Illuminate\Foundation\Http\FormRequest;

class AcceptHouseholdInvitationByTokenRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'token' => ['required', 'string'],
            'name' => ['nullable', 'string', 'max:255'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ];
    }

    protected function passedValidation(): void
    {
        $invitation = HouseholdInvitation::query()->where('token', $this->validated('token'))->firstOrFail();
        abort_unless($invitation->email === $this->user()?->email, 403, __('app.errors.invitation_not_for_you'));
    }
}
