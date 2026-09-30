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
            household_member_id: (string) $data['household_member_id'],
            split_type: SplitType::from($data['split_type']),
            amount_minor: isset($data['amount_minor'])
                ? (int) $data['amount_minor']
                : (isset($data['amount']) ? (int) round($data['amount'] * 100) : 0),
            percentage: isset($data['percentage']) ? (int) $data['percentage'] : null,
        );
    }

    public function __construct(
        public string $household_member_id,
        public SplitType $split_type,
        public int $amount_minor,
        public ?int $percentage,
    ) {}
}
