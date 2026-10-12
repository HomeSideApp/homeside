<?php

namespace App\Enums;

/**
 * Platform policy that governs whether a self-hosted installation lets its
 * members create their own households.
 *
 * This is an INSTALLATION default, not a per-user permission. It can only
 * constrain the existing decision: the per-user `can_create_households_override`
 * tri-state and the entitlement gate still apply, so `allowed` delegates and
 * `denied` refuses. It never grants anything the override or the entitlement
 * path would refuse.
 *
 * A metered installation replaces this seam with capacity entitlements; it
 * must never be read there as a capacity. The OSS default is `allowed` because a
 * self-hosted operator chose to run the install for their own household, and
 * where an entitlement gate is bound it still applies.
 */
enum HouseholdCreationPolicy: string
{
    case Allowed = 'allowed';
    case Denied = 'denied';

    /**
     * Determine whether the installation permits its members to create households.
     *
     * @return bool True when household creation is allowed by the installation.
     */
    public function allowsCreation(): bool
    {
        return $this === self::Allowed;
    }

    /**
     * Get the human-readable label for the policy.
     *
     * @return string A string value.
     */
    public function label(): string
    {
        return match ($this) {
            self::Allowed => 'Allowed',
            self::Denied => 'Denied',
        };
    }
}
