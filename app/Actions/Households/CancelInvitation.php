<?php

namespace App\Actions\Households;

use App\Enums\InvitationStatus;
use App\Models\HouseholdInvitation;
use App\Models\User;

/**
 * Cancels a pending household invitation.
 */
final class CancelInvitation
{
    /**
     * @param  HouseholdInvitation  $invitation  The invitation to cancel
     * @param  User  $user  The user cancelling the invitation
     */
    public function execute(HouseholdInvitation $invitation, User $user): void
    {
        // User can cancel their own invitation, or admin can cancel any
        if ($invitation->email !== $user->email && ! $invitation->household()->firstOrFail()->isAdmin($user)) {
            abort(403, __('app.errors.invitation_no_permission_cancel'));
        }

        if ($invitation->status !== InvitationStatus::Pending) {
            abort(409, __('app.errors.invitation_not_pending'));
        }

        $invitation->update(['status' => InvitationStatus::Cancelled]);
    }
}
