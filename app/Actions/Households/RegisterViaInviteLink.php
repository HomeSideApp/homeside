<?php

namespace App\Actions\Households;

use App\Data\Households\RegisterViaInviteLinkData;
use App\Enums\HouseholdRole;
use App\Models\Household;
use App\Models\HouseholdInviteLink;
use App\Models\User;
use App\Services\Contacts\ContactResolutionService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Registers a new verified user through a household invite link,
 * joining the household and consuming one link use.
 */
final class RegisterViaInviteLink
{
    public function __construct(private ContactResolutionService $contacts) {}

    /**
     * @param  RegisterViaInviteLinkData  $data  The registration data
     * @param  HouseholdInviteLink  $link  The invite link used to register
     * @return User The registered User value.
     */
    public function execute(RegisterViaInviteLinkData $data, HouseholdInviteLink $link): User
    {
        return DB::transaction(function () use ($data, $link): User {
            /** @var HouseholdInviteLink $link */
            $link = HouseholdInviteLink::query()->whereKey($link->id)->lockForUpdate()->firstOrFail();
            abort_unless($link->isUsable(), 422, __('app.errors.invite_link_unusable'));

            /** @var Household $household */
            $household = $link->household()->firstOrFail();

            $user = User::create([
                'name' => filled($data->name) ? $data->name : $data->email,
                'email' => $data->email,
                'password' => Hash::make($data->password),
            ]);
            // email_verified_at is not mass assignable, set it explicitly
            $user->forceFill(['email_verified_at' => now()])->save();
            $user->assignRole('user-invitation');

            $household->members()->create([
                'user_id' => $user->id,
                'role' => HouseholdRole::Member,
                'joined_at' => now(),
                'contact_id' => $this->contacts->findInMemberBooks($household, $user->email)?->id,
            ]);

            if (! $user->active_household_id) {
                $user->update(['active_household_id' => $link->household_id]);
            }

            // Keep the link creator's address book in sync with the new member
            $this->contacts->findOrCreateByEmail($link->creator, $user->email);

            $link->increment('uses_count');

            return $user;
        });
    }
}
