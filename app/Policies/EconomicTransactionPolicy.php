<?php

namespace App\Policies;

use App\Enums\TransactionScope;
use App\Models\EconomicTransaction;
use App\Models\User;
use App\Support\HouseholdAccess;

/**
 * Class EconomicTransactionPolicy
 *
 * Authorization policy for the EconomicTransaction model, governing access to viewing,
 * updating and deleting transactions based on ownership, household membership and scope.
 */
final class EconomicTransactionPolicy
{
    /**
     * Determine whether the user can view the given transaction.
     *
     * @param  User  $user  The authenticated user.
     * @param  EconomicTransaction  $transaction  The economic transaction model instance.
     * @return bool True on success, false otherwise.
     */
    public function view(User $user, EconomicTransaction $transaction): bool
    {
        if ($transaction->household_id === null) {
            return $transaction->created_by === $user->id;
        }

        if (! app(HouseholdAccess::class)->allows($user, $transaction->household_id, 'economy')) {
            return false;
        }

        if ($transaction->scope === TransactionScope::Personal) {
            return $transaction->created_by === $user->id;
        }

        return true;
    }

    /**
     * Determine whether the user can update the given transaction.
     *
     * @param  User  $user  The authenticated user.
     * @param  EconomicTransaction  $transaction  The economic transaction model instance.
     * @return bool True on success, false otherwise.
     */
    public function update(User $user, EconomicTransaction $transaction): bool
    {
        if ($transaction->household_id === null) {
            return $transaction->created_by === $user->id;
        }

        return app(HouseholdAccess::class)->allows($user, $transaction->household_id, 'economy', true)
            && $transaction->created_by === $user->id;
    }

    /**
     * Determine whether the user can delete the given transaction.
     *
     * @param  User  $user  The authenticated user.
     * @param  EconomicTransaction  $transaction  The economic transaction model instance.
     * @return bool True on success, false otherwise.
     */
    public function delete(User $user, EconomicTransaction $transaction): bool
    {
        return $this->update($user, $transaction);
    }
}
