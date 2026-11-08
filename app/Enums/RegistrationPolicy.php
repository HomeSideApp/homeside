<?php

namespace App\Enums;

/**
 * Platform policy that governs whether a registration channel may be used.
 *
 * `existing_only` is meaningful for external identity channels such as Google:
 * it means an unknown identity may not start a registration, while a known one
 * may still proceed to link or sign in. For a channel that cannot recognise an
 * identity by itself the policy collapses into `disabled`.
 */
enum RegistrationPolicy: string
{
    case Disabled = 'disabled';
    case ApprovalRequired = 'approval_required';
    case Open = 'open';
    case ExistingOnly = 'existing_only';

    /**
     * Determine whether a brand-new identity may start a registration.
     *
     * @return bool True when the policy admits new identities.
     */
    public function allowsNewIdentity(): bool
    {
        return match ($this) {
            self::Open, self::ApprovalRequired => true,
            self::Disabled, self::ExistingOnly => false,
        };
    }

    /**
     * Determine whether an allowed registration still needs approval.
     *
     * @return bool True when the registration must be approved by an administrator.
     */
    public function requiresApproval(): bool
    {
        return $this === self::ApprovalRequired;
    }

    /**
     * Get the human-readable label for the policy.
     *
     * @return string A string value.
     */
    public function label(): string
    {
        return match ($this) {
            self::Disabled => 'Disabled',
            self::ApprovalRequired => 'Approval required',
            self::Open => 'Open',
            self::ExistingOnly => 'Existing identities only',
        };
    }
}
