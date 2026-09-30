<?php

namespace App\Actions\Households;

use App\Data\Households\CreateHouseholdData;
use App\Enums\HouseholdRole;
use App\Models\Household;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Creates a household, makes the user its admin member and sets it as
 * their active household.
 */
final class CreateHousehold
{
    /**
     * @param  CreateHouseholdData  $data  The data for the new household
     * @param  User  $user  The user creating the household
     * @return Household The Household value.
     */
    public function execute(CreateHouseholdData $data, User $user): Household
    {
        return DB::transaction(function () use ($data, $user) {
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
