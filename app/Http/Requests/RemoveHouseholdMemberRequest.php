<?php

namespace App\Http\Requests;

use App\Models\Household;
use App\Models\HouseholdMember;
use Illuminate\Foundation\Http\FormRequest;

class RemoveHouseholdMemberRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Household $household */
        $household = $this->route('household');
        /** @var HouseholdMember $member */
        $member = $this->route('member');
        if ($this->routeIs('api.v1.*')) {
            abort_unless($member->household_id === $household->id, 404);
        }

        return $this->user()?->can('removeMember', [$household, $member]) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [];
    }
}
