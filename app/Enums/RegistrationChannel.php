<?php

namespace App\Enums;

/**
 * The channel through which a person can start a registration.
 *
 * Invitation channels (`HouseholdInvitation`, `AdminInvitation`) are not
 * policy-gated: the invitation itself carries its own validity rules (token,
 * expiry, remaining uses), so this channel vocabulary exists only to name the
 * flow that is asking the registration gate.
 */
enum RegistrationChannel: string
{
    case Web = 'web';
    case Api = 'api';
    case Google = 'google';
    case HouseholdInvitation = 'household_invitation';
    case AdminInvitation = 'admin_invitation';

    /**
     * Determine whether the channel is an invitation flow.
     *
     * @return bool True for the household and admin invitation channels.
     */
    public function isInvitation(): bool
    {
        return in_array($this, [self::HouseholdInvitation, self::AdminInvitation], true);
    }

    /**
     * Determine whether the channel is governed by a registration policy.
     *
     * @return bool True when the channel reads a policy from the settings store.
     */
    public function isPolicyGated(): bool
    {
        return ! $this->isInvitation();
    }

    /**
     * Get the human-readable label for the channel.
     *
     * @return string A string value.
     */
    public function label(): string
    {
        return match ($this) {
            self::Web => 'Web registration',
            self::Api => 'API registration',
            self::Google => 'Google sign-in',
            self::HouseholdInvitation => 'Household invitation',
            self::AdminInvitation => 'Administrator invitation',
        };
    }
}
