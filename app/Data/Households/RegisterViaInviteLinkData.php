<?php

namespace App\Data\Households;

/**
 * Data for registering a new user through a household invite link.
 */
final readonly class RegisterViaInviteLinkData
{
    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            token: (string) $data['token'],
            name: $data['name'] ?? null,
            email: (string) $data['email'],
            password: (string) $data['password'],
        );
    }

    public function __construct(
        public string $token,
        public ?string $name,
        public string $email,
        public string $password,
    ) {}
}
