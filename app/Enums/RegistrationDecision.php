<?php

namespace App\Enums;

/**
 * The outcome of asking the registration gate whether an identity may start a
 * registration on a channel.
 *
 * `existing_identity` and `link_required` describe identities the platform
 * already knows; they are meaningful for external identity channels such as
 * Google and can be returned by gate implementations in later phases.
 */
enum RegistrationDecision: string
{
    case Allowed = 'allowed';
    case Denied = 'denied';
    case ApprovalRequired = 'approval_required';
    case ExistingIdentity = 'existing_identity';
    case LinkRequired = 'link_required';

    /**
     * Get the human-readable label for the decision.
     *
     * @return string A string value.
     */
    public function label(): string
    {
        return match ($this) {
            self::Allowed => 'Allowed',
            self::Denied => 'Denied',
            self::ApprovalRequired => 'Approval required',
            self::ExistingIdentity => 'Existing identity',
            self::LinkRequired => 'Link required',
        };
    }
}
