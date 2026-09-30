<?php

namespace App\Actions\Economy;

use App\Data\Economy\EconomicAccountData;
use App\Models\EconomicAccount;
use App\Models\PaymentMethod;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Creates a private economic account owned by a user.
 */
final class CreateEconomicAccount
{
    /**
     * Validate and persist a new economic account with its payment methods.
     *
     * Every selected payment method must exist and be either a global catalogue entry or a custom
     * method owned by the same user.
     *
     * @param  EconomicAccountData  $data  The validated account data to persist.
     * @param  User  $user  The user that owns the new account.
     * @return EconomicAccount The created account with its payment methods loaded.
     */
    public function execute(EconomicAccountData $data, User $user): EconomicAccount
    {
        $this->assertPaymentMethodsAreAvailable($data->payment_method_ids, $user);

        $account = DB::transaction(function () use ($data, $user): EconomicAccount {
            $account = EconomicAccount::create($data->toAttributes($user->id));
            $account->paymentMethods()->sync($data->payment_method_ids);

            return $account;
        });

        return $account->load('paymentMethods');
    }

    /**
     * Assert that every selected payment method is available to the user.
     *
     * @param  array<int, string>  $paymentMethodIds  The selected payment method identifiers.
     * @param  User  $user  The user that will own the account.
     * @return void This method does not return a value.
     *
     * @throws ValidationException When a method does not exist or belongs to another user.
     */
    private function assertPaymentMethodsAreAvailable(array $paymentMethodIds, User $user): void
    {
        $availableCount = PaymentMethod::query()
            ->whereIn('id', $paymentMethodIds)
            ->visibleTo($user->id)
            ->count();

        if ($availableCount !== count(array_unique($paymentMethodIds))) {
            throw ValidationException::withMessages([
                'payment_method_ids' => __('app.validation.payment_method_not_available'),
            ]);
        }
    }
}
