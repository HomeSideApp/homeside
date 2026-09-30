<?php

namespace Tests\Concerns;

use App\Models\Household;
use App\Models\HouseholdMember;
use App\Models\User;

trait WithHousehold
{
    protected Household $household;

    /**
     * Create a household and make the given user a member with admin role.
     * Sets the household as the user's active household.
     */
    protected function createHouseholdForUser(User $user): Household
    {
        $household = Household::factory()->create(['created_by' => $user->id]);
        HouseholdMember::create([
            'user_id' => $user->id,
            'household_id' => $household->id,
            'role' => 'admin',
            'joined_at' => now(),
        ]);
        $user->update(['active_household_id' => $household->id]);

        $this->household = $household;

        return $household;
    }
}
