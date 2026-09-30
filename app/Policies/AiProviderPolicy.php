<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Household;
use App\Models\User;
use HomeSide\AiAgents\Models\AiProvider;

/**
 * Class AiProviderPolicy
 *
 * Authorization policy for the AiProvider model, governing access to viewing and
 * managing providers based on their global, user or household scope.
 */
final class AiProviderPolicy
{
    /**
     * Determine whether the user can view the given AI provider.
     *
     * @param  User  $user  The authenticated user.
     * @param  AiProvider  $provider  The AI provider model instance.
     * @return bool True on success, false otherwise.
     */
    public function view(User $user, AiProvider $provider): bool
    {
        if ($provider->isGlobal()) {
            return $user->hasRole('admin');
        }

        if ($provider->isUser()) {
            return $provider->user_id === $user->id;
        }

        return Household::find($provider->household_id)?->isMember($user) ?? false;
    }

    /**
     * Determine whether the user can manage the given AI provider.
     *
     * @param  User  $user  The authenticated user.
     * @param  AiProvider  $provider  The AI provider model instance.
     * @return bool True on success, false otherwise.
     */
    public function manage(User $user, AiProvider $provider): bool
    {
        if ($provider->isGlobal()) {
            return $user->hasRole('admin');
        }

        if ($provider->isUser()) {
            return $provider->user_id === $user->id;
        }

        return Household::find($provider->household_id)?->isAdmin($user) ?? false;
    }
}
