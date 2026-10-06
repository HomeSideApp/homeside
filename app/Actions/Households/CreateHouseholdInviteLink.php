<?php

namespace App\Actions\Households;

use App\Data\Households\CreateInviteLinkData;
use App\Enums\HouseholdAccessProfile;
use App\Models\Household;
use App\Models\HouseholdInviteLink;
use App\Models\User;
use Illuminate\Support\Str;

/**
 * Creates a reusable invitation link for a household.
 */
final class CreateHouseholdInviteLink
{
    /**
     * @param  CreateInviteLinkData  $data  The link limits data
     * @param  Household  $household  The household the link belongs to
     * @param  User  $creator  The user creating the link
     * @return HouseholdInviteLink The created HouseholdInviteLink value.
     */
    public function execute(CreateInviteLinkData $data, Household $household, User $creator): HouseholdInviteLink
    {
        return HouseholdInviteLink::create([
            ...HouseholdAccessProfile::attributes($data->access),
            'household_id' => $household->id,
            'created_by' => $creator->id,
            'token' => Str::random(48),
            'expires_at' => $data->expiresAt,
            'max_uses' => $data->maxUses,
        ]);
    }
}
