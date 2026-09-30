<?php

namespace App\Actions\Households;

use App\Enums\HouseholdRole;
use App\Enums\InvitationStatus;
use App\Models\Contact;
use App\Models\Household;
use App\Models\HouseholdInvitation;
use App\Models\User;
use App\Services\Contacts\ContactResolutionService;
use Illuminate\Support\Facades\DB;

/**
 * Accepts a pending household invitation, adding the user as a member
 * linked to the contact that led to the invitation.
 */
final class AcceptHouseholdInvitation
{
    public function __construct(private ContactResolutionService $contacts) {}

    /**
     * @param  HouseholdInvitation  $invitation  The invitation to accept
     * @param  User  $user  The user accepting the invitation
     * @return Household The Household value.
     */
    public function execute(HouseholdInvitation $invitation, User $user): Household
    {
        if ($invitation->email !== $user->email) {
            abort(403, __('app.errors.invitation_not_for_you'));
        }

        if ($invitation->status !== InvitationStatus::Pending) {
            abort(409, __('app.errors.invitation_already_processed'));
        }

        if ($invitation->expires_at->isPast()) {
            abort(409, __('app.errors.invitation_expired'));
        }

        return DB::transaction(function () use ($invitation, $user) {
            $household = $invitation->household()->firstOrFail();
            $invitation->update(['status' => InvitationStatus::Accepted, 'accepted_at' => now()]);

            $household->members()->create([
                'user_id' => $user->id,
                'role' => HouseholdRole::Member,
                'joined_at' => now(),
                'contact_id' => $this->resolveMemberContact($household, $invitation, $user),
            ]);

            // If this is the user's first household, activate it
            if (! $user->active_household_id) {
                $user->update(['active_household_id' => $invitation->household_id]);
            }

            return $household;
        });
    }

    /**
     * Resolve the contact anchor stored on the new member row.
     *
     * @param  Household  $household  The household being joined
     * @param  HouseholdInvitation  $invitation  The accepted invitation
     * @param  User  $user  The user joining the household
     * @return string|null The linked contact id, or null when no contact matches
     */
    private function resolveMemberContact(Household $household, HouseholdInvitation $invitation, User $user): ?string
    {
        if ($invitation->contact_id !== null) {
            return $invitation->contact_id;
        }

        return $this->contacts->findInMemberBooks($household, $user->email)?->id;
    }
}
