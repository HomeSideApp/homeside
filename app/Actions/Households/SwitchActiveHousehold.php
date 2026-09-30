<?php

namespace App\Actions\Households;

use App\Models\Household;
use App\Models\User;

/**
 * Sets a household as the active one for a user.
 */
final class SwitchActiveHousehold
{
    /**
     * @param  Household  $household  The household to activate
     * @param  User|string  $user  The user (or user id) switching household
     */
    public function execute(Household $household, User|string $user): void
    {
        if (is_string($user)) {
            $user = User::findOrFail($user);
        }

        if (! $household->isMember($user)) {
            abort(403);
        }

        $user->update(['active_household_id' => $household->id]);
    }
}
