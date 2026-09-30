<?php

namespace Database\Factories;

use App\Enums\TransactionScope;
use App\Enums\TransactionType;
use App\Models\EconomicTransaction;
use App\Models\Household;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EconomicTransaction>
 */
class EconomicTransactionFactory extends Factory
{
    protected $model = EconomicTransaction::class;

    public function definition(): array
    {
        return [
            'household_id' => Household::factory(),
            'created_by' => User::factory(),
            'type' => TransactionType::Expense,
            'scope' => TransactionScope::Personal,
            'title' => $this->faker->words(3, true),
            'amount_minor' => $this->faker->numberBetween(100, 20000),
            'currency' => 'EUR',
            'place' => $this->faker->optional()->company(),
            'occurred_at' => $this->faker->optional()->dateTimeThisMonth(),
            'notes' => $this->faker->optional()->sentence(),
        ];
    }

    public function forHousehold(Household $household): static
    {
        return $this->state(fn () => ['household_id' => $household->id]);
    }

    public function createdBy(User $user): static
    {
        return $this->state(fn () => ['created_by' => $user->id]);
    }

    public function private(): static
    {
        return $this->state(fn () => ['household_id' => null]);
    }

    public function shared(): static
    {
        return $this->state(fn () => ['scope' => TransactionScope::Shared]);
    }

    public function income(): static
    {
        return $this->state(fn () => ['type' => TransactionType::Income]);
    }
}
