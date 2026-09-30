<?php

namespace App\Data\Households;

/**
 * Data for inviting a member to a household by email.
 */
final readonly class InviteMemberData
{
    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            email: (string) $data['email'],
        );
    }

    public function __construct(
        public string $email,
    ) {}
}
