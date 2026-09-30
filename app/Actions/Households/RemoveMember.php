<?php

namespace App\Actions\Households;

use App\Enums\HouseholdRole;
use App\Models\Household;
use App\Models\HouseholdMember;
use Illuminate\Validation\ValidationException;

/**
 * Removes a member from a household, protecting the last admin.
 */
final class RemoveMember
{
    /**
     * @param  Household  $household  The household to remove the member from
     * @param  HouseholdMember  $member  The membership to remove
     */
    public function execute(Household $household, HouseholdMember $member): void
    {
        if ($member->role === HouseholdRole::Admin) {
            $adminCount = $household->members()
                ->where('role', HouseholdRole::Admin)
                ->count();

            if ($adminCount <= 1) {
                throw ValidationException::withMessages([
                    'member' => __('app.errors.cannot_remove_only_admin'),
                ]);
            }
        }

        $member->delete();
    }
}
