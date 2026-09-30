<?php

namespace App\Actions\Households;

use App\Models\HouseholdInviteLink;

/**
 * Revokes a reusable household invitation link.
 */
final class RevokeHouseholdInviteLink
{
    /**
     * @param  HouseholdInviteLink  $link  The link to revoke
     * @return HouseholdInviteLink The revoked HouseholdInviteLink value.
     */
    public function execute(HouseholdInviteLink $link): HouseholdInviteLink
    {
        $link->update(['revoked_at' => $link->revoked_at ?? now()]);

        return $link;
    }
}
