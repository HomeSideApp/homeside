<?php

namespace App\Actions\Economy;

use App\Data\Economy\PaymentMethodData;
use App\Models\PaymentMethod;
use Illuminate\Validation\ValidationException;

/**
 * Updates an existing payment method owned by a user.
 */
final class UpdatePaymentMethod
{
    /**
     * Apply the validated changes to a payment method.
     *
     * Global catalogue entries are read-only for regular users; only administrators may
     * update them through the administration panel.
     *
     * @param  PaymentMethod  $paymentMethod  The payment method to update.
     * @param  PaymentMethodData  $data  The validated payment method data to apply.
     * @param  bool  $allowGlobal  Whether global catalogue entries may be updated.
     * @return PaymentMethod The refreshed payment method.
     */
    public function execute(PaymentMethod $paymentMethod, PaymentMethodData $data, bool $allowGlobal = false): PaymentMethod
    {
        if ($paymentMethod->isGlobal() && ! $allowGlobal) {
            throw ValidationException::withMessages([
                'name' => __('app.validation.payment_method_is_global'),
            ]);
        }

        $paymentMethod->update($data->toAttributes($paymentMethod->user_id));

        return $paymentMethod->refresh();
    }
}
