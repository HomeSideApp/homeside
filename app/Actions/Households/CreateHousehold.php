<?php

namespace App\Actions\Households;

use App\Contracts\EntitlementGate;
use App\Data\Households\CreateHouseholdData;
use App\Enums\HouseholdRole;
use App\Models\Household;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

/**
 * Creates a household, makes the user its admin member and sets it as
 * their active household.
 *
 * Creation passes through two independent gates, in this order:
 *  1. the HouseholdPolicy `create` ability - structural authorization and the
 *     installation's local restrictions, OUTSIDE the transaction: a denial is a
 *     403 and creates nothing; and
 *  2. the entitlement gate's ownership capacity - the commercial "can this
 *     billing account own one more household?" question, INSIDE the
 *     transaction: a denial is the documented 402 (EntitlementQuotaExceededException).
 * They answer different questions and must not be folded into each other; in
 * particular, the structural policy must never read capacity, or a quota
 * refusal would surface as a 403 before this action ever runs.
 */
final class CreateHousehold
{
    public function __construct(
        private readonly EntitlementGate $entitlements,
    ) {}

    /**
     * @param  CreateHouseholdData  $data  The data for the new household
     * @param  User  $user  The user creating the household
     * @return Household The Household value.
     */
    public function execute(CreateHouseholdData $data, User $user): Household
    {
        // Layer 1, before the transaction: structural authorization. A denial
        // here creates nothing, never opens a transaction and renders as a 403.
        Gate::forUser($user)->authorize('create', Household::class);

        return DB::transaction(function () use ($data, $user) {
            // Layer 2, INSIDE the transaction: commercial capacity. This is the
            // authoritative quota refusal, and it renders as the documented 402
            // (EntitlementQuotaExceededException), not a 403. The bound
            // entitlement gate locks the user's billing-account row as its
            // serialization anchor and counts owned households under that lock,
            // so two simultaneous attempts at the last available slot collapse
            // into one: the loser re-reads the committed count after the winner
            // commits and is refused. This call must stay inside the
            // transaction; a plain count-then-insert outside it would let both
            // racers pass at the last slot.
            $this->entitlements->assertHouseholdOwnershipCapacity($user);

            $household = Household::create([
                'name' => $data->name,
                'description' => $data->description,
                'color' => $data->color,
                'invite_code' => strtoupper(Str::random(8)),
                'created_by' => $user->id,
            ]);

            $household->members()->create([
                'user_id' => $user->id,
                'role' => HouseholdRole::Admin,
                'joined_at' => now(),
            ]);

            $user->update(['active_household_id' => $household->id]);

            return $household;
        });
    }
}
