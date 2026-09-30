<?php

namespace App\Policies;

use App\Models\EconomicAccount;
use App\Models\User;

/**
 * Class EconomicAccountPolicy
 *
 * Authorization policy for the EconomicAccount model. Accounts are always private to their
 * owner, so every ability checks the ownership strictly and no household scope is considered.
 */
final class EconomicAccountPolicy
{
    /**
     * Determine whether the user can list their economic accounts.
     *
     * @param  User  $user  The authenticated user.
     * @return bool True when the user may view the accounts page.
     */
    public function viewAny(User $user): bool
    {
        return $user->getPermissionRouteNames()->contains('economy.me.accounts.index');
    }

    /**
     * Determine whether the user can view the given account.
     *
     * @param  User  $user  The authenticated user.
     * @param  EconomicAccount  $account  The economic account model instance.
     * @return bool True when the account belongs to the user.
     */
    public function view(User $user, EconomicAccount $account): bool
    {
        return $account->user_id === $user->id;
    }

    /**
     * Determine whether the user can create an economic account.
     *
     * @param  User  $user  The authenticated user.
     * @return bool True when the user may create accounts.
     */
    public function create(User $user): bool
    {
        return $user->getPermissionRouteNames()->contains('economy.me.accounts.store');
    }

    /**
     * Determine whether the user can update the given account.
     *
     * @param  User  $user  The authenticated user.
     * @param  EconomicAccount  $account  The economic account model instance.
     * @return bool True when the account belongs to the user.
     */
    public function update(User $user, EconomicAccount $account): bool
    {
        return $account->user_id === $user->id
            && $user->getPermissionRouteNames()->contains('economy.me.accounts.update');
    }

    /**
     * Determine whether the user can archive or restore the given account.
     *
     * @param  User  $user  The authenticated user.
     * @param  EconomicAccount  $account  The economic account model instance.
     * @return bool True when the account belongs to the user.
     */
    public function archive(User $user, EconomicAccount $account): bool
    {
        return $account->user_id === $user->id
            && $user->getPermissionRouteNames()->contains('economy.me.accounts.archive');
    }

    /**
     * Determine whether the user can delete the given account.
     *
     * @param  User  $user  The authenticated user.
     * @param  EconomicAccount  $account  The economic account model instance.
     * @return bool True when the account belongs to the user.
     */
    public function delete(User $user, EconomicAccount $account): bool
    {
        return $account->user_id === $user->id
            && $user->getPermissionRouteNames()->contains('economy.me.accounts.destroy');
    }
}
