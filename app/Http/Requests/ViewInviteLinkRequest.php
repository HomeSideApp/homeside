<?php

namespace App\Http\Requests;

use App\Models\HouseholdInviteLink;
use Illuminate\Foundation\Http\FormRequest;

class ViewInviteLinkRequest extends FormRequest
{
    public function authorize(): bool
    {
        abort_unless($this->inviteLink()->isUsable(), 404, __('app.errors.invite_link_unusable'));

        return true;
    }

    public function inviteLink(): HouseholdInviteLink
    {
        return HouseholdInviteLink::query()->where('token', $this->route('token'))->firstOrFail();
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [];
    }
}
