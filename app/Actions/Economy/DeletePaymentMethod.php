<?php

namespace App\Actions\Economy;

use App\Models\PaymentMethod;
use Illuminate\Validation\ValidationException;

/**
 * Deletes a payment method owned by a user.
 */
final class DeletePaymentMethod
{
    /**
     * Delete a custom payment method, refusing when accounts still use it.
     *
     * Refusing the deletion keeps the referential integrity of accounts intact and forces the
     * user to reassign or archive the accounts that depend on the method first.
     *
     * @param  PaymentMethod  $paymentMethod  The payment method to delete.
     * @param  bool  $allowGlobal  Whether global catalogue entries may be deleted.
     * @return void This action does not return a value.
     */
    public function execute(PaymentMethod $paymentMethod, bool $allowGlobal = false): void
    {
        if ($paymentMethod->isGlobal() && ! $allowGlobal) {
            throw ValidationException::withMessages([
                'name' => __('app.validation.payment_method_is_global'),
            ]);
        }

        if ($paymentMethod->accounts()->exists()) {
            throw ValidationException::withMessages([
                'name' => __('app.validation.payment_method_in_use'),
            ]);
        }

        $paymentMethod->delete();
    }
}
