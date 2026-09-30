<?php

namespace App\Actions\Households;

use App\Models\Household;

/**
 * Loads a household with its members, creator and pending invitations.
 */
final class GetHousehold
{
    /**
     * @param  Household  $household  The household to load
     * @return Household The Household value.
     */
    public function execute(Household $household): Household
    {
        return $household->load([
            'members.user',
            'creator',
            'pendingInvitations.inviter',
        ]);
    }
}
