<?php

namespace App\Actions\Households;

use App\Enums\HouseholdRole;
use App\Enums\InvitationStatus;
use App\Models\Household;
use App\Models\HouseholdInvitation;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Accepts a household invitation by token, adding the user as a member.
 */
final class AcceptInvitation
{
    /**
     * @param  string  $token  The invitation token
     * @param  User|string  $user  The user (or user id) accepting the invitation
     * @return Household The Household value.
     */
    public function execute(string $token, User|string $user): Household
    {
        if (is_string($user)) {
            $user = User::query()->whereKey($user)->firstOrFail();
        }

        $invitation = HouseholdInvitation::query()->where('token', $token)->firstOrFail();

        if ($invitation->email !== $user->email) {
            abort(403, __('app.errors.invitation_not_for_you'));
        }

        if ($invitation->status !== InvitationStatus::Pending || $invitation->accepted_at !== null) {
            abort(409, __('app.errors.invitation_already_processed'));
        }

        if ($invitation->expires_at->isPast()) {
            abort(409, __('app.errors.invitation_expired'));
        }

        return DB::transaction(function () use ($invitation, $user) {
            $household = $invitation->household()->firstOrFail();
            $invitation->update([
                'status' => InvitationStatus::Accepted,
                'accepted_at' => now(),
            ]);

            $household->members()->create([
                'user_id' => $user->id,
                'role' => HouseholdRole::Member,
                'joined_at' => now(),
            ]);

            if (! $user->active_household_id) {
                $user->update(['active_household_id' => $invitation->household_id]);
            }

            return $household;
        });
    }
}
