<?php

namespace App\Policies;

use App\Models\Household;
use App\Models\HouseholdMember;
use App\Models\User;
use App\Support\PlatformAccess;

/**
 * Class HouseholdPolicy
 *
 * Authorization policy for the Household model, governing access to household
 * views, updates, deletion, management, invitations and member removal.
 */
final class HouseholdPolicy
{
    /**
     * Determine whether the user can create a household.
     *
     * This is the STRUCTURAL gate: it answers whether the installation policy
     * and the user's own restrictions allow a new household at all. Commercial
     * capacity is a different question and belongs to the entitlement gate,
     * which the creation action asserts inside its transaction. The policy must
     * never try to count or cap households itself; it only delegates the read
     * through PlatformAccess, the single funnel every surface uses.
     *
     * @param  User  $user  The authenticated user.
     * @return bool True on success, false otherwise.
     */
    public function create(User $user): bool
    {
        return app(PlatformAccess::class)->canCreateHouseholds($user);
    }

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
        return $household->owner_id === $user->id && $household->isAdmin($user);
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
        if ($member->household_id !== $household->id) {
            return false;
        }

        if ($household->isAdmin($user)) {
            return true;
        }

        return $member->user_id === $user->id;
    }
}
