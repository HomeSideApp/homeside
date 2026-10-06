<?php

namespace App\Http\Requests;

use App\Models\Household;
use App\Models\HouseholdMember;
use App\Support\HouseholdAccess;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class LinkHouseholdMemberContactRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        /** @var Household $household */
        $household = $this->route('household');
        /** @var HouseholdMember $member */
        $member = $this->route('member');
        if ($user === null || ! $user->can('view', $household)) {
            return false;
        }

        abort_unless($member->household_id === $household->id, 404);

        return $household->isAdmin($user) || $member->user_id === $user->id;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $user = $this->user();
        abort_unless($user !== null, 403);

        return [
            'contact_id' => ['nullable', 'uuid', Rule::exists('contacts', 'id')->where(fn ($query) => $query
                ->where('user_id', $user->id)
                ->orWhereIn('household_id', app(HouseholdAccess::class)->householdIds($user, 'contacts')))],
        ];
    }
}
