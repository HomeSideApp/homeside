<?php

namespace App\Actions\Households;

use App\Contracts\EntitlementGate;
use App\Enums\HouseholdRole;
use App\Models\Household;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class TransferHouseholdOwnership
{
    public function __construct(
        private readonly EntitlementGate $entitlements,
    ) {}

    /**
     * Transfer ownership to an existing admin member.
     *
     * The receiving user's capacity is asserted through the same entitlement
     * contract as creation, INSIDE this transaction: taking ownership consumes
     * one of the target's own households_own slots. The target's billing-account
     * row is the serialization anchor, so a transfer racing a creation or
     * another transfer for the same target cannot both slip past the last slot.
     *
     * The structural safeguards stay here too: the actor must still be the
     * locked household's owner (a stale owner is refused), and the target must
     * be an admin member, so the household always keeps an administrator.
     */
    public function execute(Household $household, User $actor, string $memberId): Household
    {
        return DB::transaction(function () use ($household, $actor, $memberId): Household {
            $locked = Household::query()->lockForUpdate()->findOrFail($household->id);
            abort_unless($locked->owner_id === $actor->id, 403);
            $member = $locked->members()->lockForUpdate()->findOrFail($memberId);
            $target = User::query()->lockForUpdate()->findOrFail($member->user_id);
            if ($member->role !== HouseholdRole::Admin || $member->user_id === $actor->id || $target->approval_status !== 'approved' || ! $target->households_enabled) {
                throw ValidationException::withMessages(['member_id' => __('validation.in', ['attribute' => 'member_id'])]);
            }

            // Enforce the target's households_own quota inside the transaction
            // so a denial rolls back atomically and the owner_id update never
            // lands (no-op while SaaS is disabled).
            $this->entitlements->assertHouseholdOwnershipCapacity($target);

            $locked->update(['owner_id' => $member->user_id]);

            return $locked;
        }, 3);
    }
}
