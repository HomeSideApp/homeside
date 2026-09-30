<?php

namespace App\Policies;

use App\Models\EconomicDocument;
use App\Models\User;

/**
 * Class EconomicDocumentPolicy
 *
 * Authorization policy for the EconomicDocument model, governing access to viewing
 * and deleting uploaded documents based on ownership or household membership.
 */
final class EconomicDocumentPolicy
{
    /**
     * Determine whether the user can view the given document.
     *
     * @param  User  $user  The authenticated user.
     * @param  EconomicDocument  $document  The economic document model instance.
     * @return bool True on success, false otherwise.
     */
    public function view(User $user, EconomicDocument $document): bool
    {
        if ($document->household_id === null) {
            return $document->uploaded_by === $user->id;
        }

        return $document->household->isMember($user);
    }

    /**
     * Determine whether the user can delete the given document.
     *
     * @param  User  $user  The authenticated user.
     * @param  EconomicDocument  $document  The economic document model instance.
     * @return bool True on success, false otherwise.
     */
    public function delete(User $user, EconomicDocument $document): bool
    {
        if ($document->household_id === null) {
            return $document->uploaded_by === $user->id;
        }

        return $document->uploaded_by === $user->id
            || $document->household->isAdmin($user);
    }
}
