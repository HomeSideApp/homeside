<?php

namespace App\Policies;

use App\Models\User;

/**
 * Class AiGlobalSettingPolicy
 *
 * Authorization policy for the global AI settings, restricting viewing and
 * management to users with the admin role.
 */
final class AiGlobalSettingPolicy
{
    /**
     * Determine whether the user can view the global AI settings.
     *
     * @param  User  $user  The authenticated user.
     * @return bool True on success, false otherwise.
     */
    public function view(User $user): bool
    {
        return $user->hasRole('admin');
    }

    /**
     * Determine whether the user can manage the global AI settings.
     *
     * @param  User  $user  The authenticated user.
     * @return bool True on success, false otherwise.
     */
    public function manage(User $user): bool
    {
        return $user->hasRole('admin');
    }
}
