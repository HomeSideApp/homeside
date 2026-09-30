<?php

namespace App\Actions\Households;

use App\Data\Households\InviteMemberData;
use App\Enums\InvitationStatus;
use App\Models\Household;
use App\Models\HouseholdInvitation;
use App\Models\User;
use App\Notifications\HouseholdInvitationNotification;
use App\Notifications\HouseholdInviteNewUserNotification;
use App\Services\Contacts\ContactResolutionService;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Invites a member to a household by email, cancelling any previous
 * pending invitations, linking the inviter's contact and notifying the invited user.
 */
final class InviteMember
{
    public function __construct(private ContactResolutionService $contacts) {}

    /**
     * @param  InviteMemberData  $data  The invitation data
     * @param  Household  $household  The household to invite into
     * @param  User  $inviter  The user sending the invitation
     * @return HouseholdInvitation The HouseholdInvitation value.
     */
    public function execute(InviteMemberData $data, Household $household, User $inviter): HouseholdInvitation
    {
        $existingUser = User::where('email', $data->email)->first();
        if ($existingUser && $household->isMember($existingUser)) {
            throw ValidationException::withMessages([
                'email' => 'Este usuario ya es miembro del hogar.',
            ]);
        }

        // Link the invitation to the inviter's contact, creating a local one when missing
        $contact = $this->contacts->findOrCreateByEmail($inviter, $data->email);

        // Cancel any previous pending/expired invitations for this email in this household
        HouseholdInvitation::where('household_id', $household->id)
            ->where('email', $data->email)
            ->whereIn('status', [InvitationStatus::Pending, InvitationStatus::Expired])
            ->update(['status' => InvitationStatus::Cancelled]);

        $invitation = HouseholdInvitation::create([
            'household_id' => $household->id,
            'invited_by' => $inviter->id,
            'email' => $data->email,
            'contact_id' => $contact->id,
            'status' => InvitationStatus::Pending,
            'token' => Str::random(64),
            'expires_at' => now()->addDays(7),
        ]);

        // Send appropriate email notification
        if ($existingUser) {
            $existingUser->notify(new HouseholdInvitationNotification($invitation));
        } else {
            // Create a temporary user with the user-invitation role
            $newUser = User::create([
                'name' => $data->email,
                'email' => $data->email,
                'password' => Hash::make(Str::random(16)),
            ]);
            $newUser->assignRole('user-invitation');

            $newUser->notify(new HouseholdInviteNewUserNotification($invitation));
        }

        return $invitation;
    }
}
