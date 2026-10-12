<?php

namespace App\Policies;

use App\Models\User;

/**
 * Authorization for the platform registration-settings surface.
 *
 * The settings are an installation-wide policy, so they are guarded by a single
 * dedicated permission (`admin.registrations.manage-settings`) rather than the
 * approval abilities. Viewing and updating therefore share one gate, and a
 * suspended or blocked administrator loses both because effective access is
 * required.
 */
final class RegistrationSettingsPolicy
{
    /**
     * Determine whether the actor may view the settings page.
     */
    public function view(User $actor): bool
    {
        return $this->allowed($actor);
    }

    /**
     * Determine whether the actor may persist new settings.
     */
    public function update(User $actor): bool
    {
        return $this->allowed($actor);
    }

    /**
     * Require effective access to the application plus the settings permission.
     */
    private function allowed(User $actor): bool
    {
        return $actor->canAccessApplication()
            && $actor->getPermissionRouteNames()->contains('admin.registrations.manage-settings');
    }
}
