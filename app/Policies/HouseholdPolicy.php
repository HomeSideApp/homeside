<?php

namespace App\Policies;

use App\Models\Household;
use App\Models\HouseholdMember;
use App\Models\User;

/**
 * Class HouseholdPolicy
 *
 * Authorization policy for the Household model, governing access to household
 * views, updates, deletion, management, invitations and member removal.
 */
final class HouseholdPolicy
{
    /**
     * Determine whether the user can view the given household.
     *
     * @param  User  $user  The authenticated user.
     * @param  Household  $household  The household model instance.
     * @return bool True on success, false otherwise.
     */
    public function view(User $user, Household $household): bool
    {
        return $household->isMember($user);
    }

    /**
     * Determine whether the user can update the given household.
     *
     * @param  User  $user  The authenticated user.
     * @param  Household  $household  The household model instance.
     * @return bool True on success, false otherwise.
     */
    public function update(User $user, Household $household): bool
    {
        return $household->isAdmin($user);
    }

    /**
     * Determine whether the user can delete the given household.
     *
     * @param  User  $user  The authenticated user.
     * @param  Household  $household  The household model instance.
     * @return bool True on success, false otherwise.
     */
    public function delete(User $user, Household $household): bool
    {
        return $household->isAdmin($user);
    }

    /**
     * Determine whether the user can manage the given household.
     *
     * @param  User  $user  The authenticated user.
     * @param  Household  $household  The household model instance.
     * @return bool True on success, false otherwise.
     */
    public function manage(User $user, Household $household): bool
    {
        return $household->isAdmin($user);
    }

    /**
     * Determine whether the user can invite members to the given household.
     *
     * @param  User  $user  The authenticated user.
     * @param  Household  $household  The household model instance.
     * @return bool True on success, false otherwise.
     */
    public function invite(User $user, Household $household): bool
    {
        return $household->isAdmin($user);
    }

    /**
     * Determine whether the user can remove the given member from the household.
     *
     * @param  User  $user  The authenticated user.
     * @param  Household  $household  The household model instance.
     * @param  HouseholdMember  $member  The household member model instance.
     * @return bool True on success, false otherwise.
     */
    public function removeMember(User $user, Household $household, HouseholdMember $member): bool
    {
        if ($household->isAdmin($user)) {
            return true;
        }

        return $member->user_id === $user->id;
    }
}
