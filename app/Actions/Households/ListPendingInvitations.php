<?php

namespace App\Actions\Households;

use App\Models\HouseholdInvitation;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Lists the pending invitations for a user's email.
 */
final class ListPendingInvitations
{
    /**
     * @param  User  $user  The user to list invitations for
     * @return Collection<int, HouseholdInvitation>
     */
    public function execute(User $user): Collection
    {
        return HouseholdInvitation::where('email', $user->email)
            ->pending()
            ->with('household')
            ->with('inviter')
            ->get();
    }
}
