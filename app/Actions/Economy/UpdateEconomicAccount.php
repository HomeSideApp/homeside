<?php

namespace App\Actions\Economy;

use App\Data\Economy\EconomicAccountData;
use App\Models\EconomicAccount;
use App\Models\PaymentMethod;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Updates an existing private economic account.
 */
final class UpdateEconomicAccount
{
    /**
     * Validate and apply the changes to an economic account, replacing its payment methods.
     *
     * @param  EconomicAccount  $account  The account to update.
     * @param  EconomicAccountData  $data  The validated account data to apply.
     * @return EconomicAccount The refreshed account with its payment methods loaded.
     */
    public function execute(EconomicAccount $account, EconomicAccountData $data): EconomicAccount
    {
        $availableCount = PaymentMethod::query()
            ->whereIn('id', $data->payment_method_ids)
            ->visibleTo($account->user_id)
            ->count();

        if ($availableCount !== count(array_unique($data->payment_method_ids))) {
            throw ValidationException::withMessages([
                'payment_method_ids' => __('app.validation.payment_method_not_available'),
            ]);
        }

        DB::transaction(function () use ($account, $data): void {
            $account->update($data->toAttributes($account->user_id));
            $account->paymentMethods()->sync($data->payment_method_ids);
        });

        return $account->refresh()->load('paymentMethods');
    }
}
