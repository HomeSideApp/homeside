<?php

namespace App\Actions\Economy;

use App\Data\Economy\PaymentMethodData;
use App\Models\PaymentMethod;
use App\Models\User;
use Illuminate\Support\Str;

/**
 * Creates a custom payment method owned by a user.
 */
final class CreatePaymentMethod
{
    /**
     * Create a payment method for the given user.
     *
     * The slug is derived from the name and made unique by appending a numeric suffix when
     * the base slug is already taken by another user or by a global catalogue entry.
     *
     * @param  PaymentMethodData  $data  The validated payment method data to persist.
     * @param  User  $user  The user that owns the new payment method.
     * @return PaymentMethod The created payment method.
     */
    public function execute(PaymentMethodData $data, User $user): PaymentMethod
    {
        return PaymentMethod::create([
            ...$data->toAttributes($user->id),
            'slug' => $this->uniqueSlug($data->name),
        ]);
    }

    /**
     * Build a unique slug for a user payment method.
     *
     * @param  string  $name  The payment method name used as the slug base.
     * @return string The unique slug for the payment method.
     */
    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'payment-method';
        $slug = $base;
        $suffix = 2;

        while (PaymentMethod::query()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$suffix;
            $suffix++;
        }

        return $slug;
    }
}
