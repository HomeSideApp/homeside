<?php

namespace App\Actions\Households;

use App\Enums\HouseholdRole;
use App\Models\Household;
use App\Models\HouseholdMember;
use Illuminate\Support\Facades\DB;
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
        DB::transaction(function () use ($household, $member): void {
            $household = Household::query()->lockForUpdate()->findOrFail($household->id);
            $member = $household->members()->lockForUpdate()->findOrFail($member->id);
            if ($household->owner_id === $member->user_id) {
                throw ValidationException::withMessages(['member' => __('app.permissions.owner_transfer_required')]);
            }
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
        }, 3);
    }
}
