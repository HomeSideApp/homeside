<?php

namespace App\Policies;

use App\Models\EconomicImport;
use App\Models\User;

/**
 * Class EconomicImportPolicy
 *
 * Authorization policy for the EconomicImport model, governing access to viewing,
 * confirming, retrying and discarding imports based on ownership or household membership.
 */
final class EconomicImportPolicy
{
    /**
     * Determine whether the user owns or is a member of the import's household.
     */
    private function ownsOrFail(User $user, EconomicImport $import): bool
    {
        if ($import->household_id === null) {
            return $import->created_by === $user->id;
        }

        return $import->household->isMember($user);
    }

    /**
     * Determine whether the user can view the given import.
     *
     * @param  User  $user  The authenticated user.
     * @param  EconomicImport  $import  The economic import model instance.
     * @return bool True on success, false otherwise.
     */
    public function view(User $user, EconomicImport $import): bool
    {
        return $this->ownsOrFail($user, $import);
    }

    /**
     * Determine whether the user can confirm the given import.
     *
     * @param  User  $user  The authenticated user.
     * @param  EconomicImport  $import  The economic import model instance.
     * @return bool True on success, false otherwise.
     */
    public function confirm(User $user, EconomicImport $import): bool
    {
        return $this->ownsOrFail($user, $import);
    }

    /**
     * Determine whether the user can retry the given import.
     *
     * @param  User  $user  The authenticated user.
     * @param  EconomicImport  $import  The economic import model instance.
     * @return bool True on success, false otherwise.
     */
    public function retry(User $user, EconomicImport $import): bool
    {
        return $this->ownsOrFail($user, $import);
    }

    /**
     * Determine whether the user can discard the given import.
     *
     * @param  User  $user  The authenticated user.
     * @param  EconomicImport  $import  The economic import model instance.
     * @return bool True on success, false otherwise.
     */
    public function discard(User $user, EconomicImport $import): bool
    {
        return $this->ownsOrFail($user, $import);
    }
}
