<?php

namespace Database\Factories;

use App\Enums\PaymentMethodKind;
use App\Models\PaymentMethod;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<PaymentMethod>
 */
class PaymentMethodFactory extends Factory
{
    protected $model = PaymentMethod::class;

    /**
     * Define the default state of a payment method.
     *
     * @return array<string, mixed> The default model attributes.
     */
    public function definition(): array
    {
        $name = $this->faker->unique()->words(2, true);

        return [
            'user_id' => User::factory(),
            'slug' => Str::slug($name).'-'.$this->faker->unique()->numerify('####'),
            'name' => Str::title($name),
            'icon' => null,
            'color' => '#9E9E9E',
            'kind' => PaymentMethodKind::Other,
            'is_active' => true,
            'sort_order' => 0,
        ];
    }

    /**
     * Configure the payment method as a global catalogue entry.
     *
     * @return static The factory instance configured with a null owner.
     */
    public function global(): static
    {
        return $this->state(fn (): array => ['user_id' => null]);
    }

    /**
     * Configure the payment method as owned by the given user.
     *
     * @param  User  $user  The user that owns the payment method.
     * @return static The factory instance configured with the owner.
     */
    public function forUser(User $user): static
    {
        return $this->state(fn (): array => ['user_id' => $user->id]);
    }

    /**
     * Configure the payment method category.
     *
     * @param  PaymentMethodKind  $kind  The category to apply.
     * @return static The factory instance configured with the category.
     */
    public function kind(PaymentMethodKind $kind): static
    {
        return $this->state(fn (): array => ['kind' => $kind]);
    }
}
