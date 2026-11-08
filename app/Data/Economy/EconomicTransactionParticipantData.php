<?php

namespace App\Data\Economy;

use App\Enums\SplitType;

/**
 * Data for a participant split of an economic transaction.
 */
final readonly class EconomicTransactionParticipantData
{
    public static function fromArray(array $data): self
    {
        return new self(
            household_member_id: $data['household_member_id'] ?? null,
            split_type: SplitType::from($data['split_type']),
            amount_minor: isset($data['amount_minor'])
                ? (int) $data['amount_minor']
                : (isset($data['amount']) ? (int) round($data['amount'] * 100) : 0),
            percentage: isset($data['percentage']) ? (int) $data['percentage'] : null,
            contact_id: $data['contact_id'] ?? null,
            display_name: $data['display_name'] ?? null,
            share_count: max(1, (int) ($data['share_count'] ?? 1)),
        );
    }

    public function __construct(
        public ?string $household_member_id,
        public SplitType $split_type,
        public int $amount_minor,
        public ?int $percentage,
        public ?string $contact_id = null,
        public ?string $display_name = null,
        public int $share_count = 1,
    ) {}

    /**
     * Build the stable identity of the participant, mirroring the database generated column.
     *
     * @return string The member identifier, the lowercased external name prefixed with "ext:", or "anon".
     */
    public function identityKey(): string
    {
        if ($this->household_member_id !== null && $this->household_member_id !== '') {
            return $this->household_member_id;
        }

        $name = mb_strtolower(trim((string) $this->display_name));

        return $name !== '' ? 'ext:'.$name : 'anon';
    }

    /**
     * Determine whether the participant represents an unnamed portion of the split.
     *
     * @return bool True when there is no member identifier and no display name; otherwise false.
     */
    public function isAnonymous(): bool
    {
        return $this->identityKey() === 'anon';
    }
}
