<?php

namespace App\Data\Households;

use App\Enums\HouseholdAccessProfile;

/**
 * Data for inviting a member to a household by email.
 */
final readonly class InviteMemberData
{
    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            access: HouseholdAccessProfile::attributes($data),
            email: (string) $data['email'],
        );
    }

    /** @param array{access_profile?: string, module_access?: array<string, string>} $access */
    public function __construct(
        public string $email,
        public array $access = [],
    ) {}
}
