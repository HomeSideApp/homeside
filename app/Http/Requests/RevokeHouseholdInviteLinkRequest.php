<?php

namespace App\Http\Requests;

use App\Models\Household;
use App\Models\HouseholdInviteLink;

class RevokeHouseholdInviteLinkRequest extends ManageHouseholdRequest
{
    public function authorize(): bool
    {
        if (! parent::authorize()) {
            return false;
        }

        /** @var HouseholdInviteLink $link */
        $link = $this->route('link');
        /** @var Household $household */
        $household = $this->route('household');
        abort_unless($link->household_id === $household->id, 404);

        return true;
    }
}
