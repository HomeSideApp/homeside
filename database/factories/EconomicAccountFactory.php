<?php

namespace Database\Factories;

use App\Models\EconomicAccount;
use App\Models\PaymentMethod;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<EconomicAccount>
 */
class EconomicAccountFactory extends Factory
{
    protected $model = EconomicAccount::class;

    /**
     * Define the default state of an economic account.
     *
     * @return array<string, mixed> The default model attributes.
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => Str::title($this->faker->unique()->words(2, true)),
            'currency' => 'EUR',
            'crypto_asset_id' => null,
            'decimal_places' => 2,
            'initial_balance_minor' => $this->faker->numberBetween(0, 500000),
            'initial_balance_at' => null,
            'icon' => null,
            'color' => '#6366F1',
            'last_four_digits' => null,
            'include_in_totals' => true,
            'archived_at' => null,
        ];
    }

    /**
     * Attach a default payment method to every created account.
     *
     * Accounts accept one or more payment methods, so the factory links a single one unless the
     * caller attaches its own set through a state.
     *
     * @return static The factory instance configured with its after-creating hook.
     */
    public function configure(): static
    {
        return $this->afterCreating(function (EconomicAccount $account): void {
            $account->paymentMethods()->syncWithoutDetaching([
                PaymentMethod::factory()->create()->id,
            ]);
        });
    }

    /**
     * Configure the account as owned by the given user.
     *
     * @param  User  $user  The user that owns the account.
     * @return static The factory instance configured with the owner.
     */
    public function forUser(User $user): static
    {
        return $this->state(fn (): array => ['user_id' => $user->id]);
    }

    /**
     * Configure the account currency and decimal precision.
     *
     * @param  string  $currency  The currency code or crypto symbol.
     * @param  int  $decimalPlaces  The number of minor units per currency unit.
     * @return static The factory instance configured with the currency.
     */
    public function currency(string $currency, int $decimalPlaces = 2): static
    {
        return $this->state(fn (): array => [
            'currency' => $currency,
            'decimal_places' => $decimalPlaces,
        ]);
    }

    /**
     * Attach the given payment methods to the created account.
     *
     * @param  array<int, PaymentMethod>  $paymentMethods  The payment methods to attach.
     * @return static The factory instance configured with the payment methods.
     */
    public function withPaymentMethods(array $paymentMethods): static
    {
        return $this->afterCreating(function (EconomicAccount $account) use ($paymentMethods): void {
            $account->paymentMethods()->sync(
                array_map(static fn (PaymentMethod $method): string => $method->id, $paymentMethods),
            );
        });
    }

    /**
     * Configure the account as archived.
     *
     * @return static The factory instance configured as archived.
     */
    public function archived(): static
    {
        return $this->state(fn (): array => ['archived_at' => now()]);
    }
}
