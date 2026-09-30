<?php

namespace App\Data\Households;

use Illuminate\Support\Carbon;

/**
 * Data for creating a reusable household invite link.
 */
final readonly class CreateInviteLinkData
{
    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            expiresAt: ! empty($data['expires_at']) ? Carbon::parse($data['expires_at']) : null,
            maxUses: ! empty($data['max_uses']) ? (int) $data['max_uses'] : null,
        );
    }

    public function __construct(
        public ?Carbon $expiresAt,
        public ?int $maxUses,
    ) {}
}
