<?php

namespace App\Enums;

/**
 * Platform policy that governs whether an invitation channel may start a
 * registration.
 *
 * Invitations are the normal onboarding path of a private installation: the
 * operator already decided to let these people in, so both invitation channels
 * default to enabled. Unlike RegistrationPolicy there is deliberately no
 * `approval_required` state, because an invitation IS the operator's decision;
 * the only meaningful choice is whether the channel is usable at all.
 */
enum InvitationPolicy: string
{
    case Enabled = 'enabled';
    case Disabled = 'disabled';

    /**
     * Determine whether the channel may start a registration.
     *
     * @return bool True when the invitation channel is enabled.
     */
    public function allowsRegistration(): bool
    {
        return $this === self::Enabled;
    }

    /**
     * Get the human-readable label for the policy.
     *
     * @return string A string value.
     */
    public function label(): string
    {
        return match ($this) {
            self::Enabled => 'Enabled',
            self::Disabled => 'Disabled',
        };
    }
}
