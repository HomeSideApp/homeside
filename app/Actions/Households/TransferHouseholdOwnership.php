<?php

namespace App\Actions\Households;

use App\Enums\HouseholdRole;
use App\Models\Household;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class TransferHouseholdOwnership
{
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
            $locked->update(['owner_id' => $member->user_id]);

            return $locked;
        }, 3);
    }
}
