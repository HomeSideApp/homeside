<?php

namespace Database\Factories;

use App\Enums\SplitType;
use App\Models\EconomicTransaction;
use App\Models\EconomicTransactionParticipant;
use App\Models\HouseholdMember;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EconomicTransactionParticipant>
 */
class EconomicTransactionParticipantFactory extends Factory
{
    protected $model = EconomicTransactionParticipant::class;

    public function definition(): array
    {
        return [
            'transaction_id' => EconomicTransaction::factory(),
            'household_member_id' => HouseholdMember::factory(),
            'split_type' => SplitType::Equal,
            'amount_minor' => $this->faker->numberBetween(100, 5000),
            'percentage' => null,
        ];
    }

    public function fixed(int $amountMinor): static
    {
        return $this->state(fn () => [
            'split_type' => SplitType::Fixed,
            'amount_minor' => $amountMinor,
        ]);
    }
}
