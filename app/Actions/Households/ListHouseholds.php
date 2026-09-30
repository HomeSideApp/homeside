<?php

namespace App\Actions\Households;

use App\Models\Household;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

/**
 * Lists the households a user belongs to, with their member counts.
 */
final class ListHouseholds
{
    /**
     * @param  User|string  $user  The user or user id
     * @return Collection<int, Household>
     */
    public function execute(User|string $user): Collection
    {
        if (is_string($user)) {
            $user = User::query()->whereKey($user)->firstOrFail();
        }

        return $user->households()
            ->withCount('members')
            ->orderBy('name')
            ->get();
    }
}
