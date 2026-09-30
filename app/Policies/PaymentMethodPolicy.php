<?php

namespace App\Policies;

use App\Models\PaymentMethod;
use App\Models\User;

/**
 * Class PaymentMethodPolicy
 *
 * Authorization policy for the PaymentMethod model. Custom methods are strictly private to
 * their owner, while global catalogue entries are readable by everyone and writable only by
 * administrators.
 */
final class PaymentMethodPolicy
{
    /**
     * Determine whether the user can list the available payment methods.
     *
     * @param  User  $user  The authenticated user.
     * @return bool True when the user may reach the payment method pages.
     */
    public function viewAny(User $user): bool
    {
        $permissions = $user->getPermissionRouteNames();

        return $permissions->contains('economy.me.accounts.index')
            || $permissions->contains('households.economy.index');
    }

    /**
     * Determine whether the user can view the given payment method.
     *
     * @param  User  $user  The authenticated user.
     * @param  PaymentMethod  $paymentMethod  The payment method model instance.
     * @return bool True when the method is global or owned by the user.
     */
    public function view(User $user, PaymentMethod $paymentMethod): bool
    {
        return $paymentMethod->isGlobal() || $paymentMethod->user_id === $user->id;
    }

    /**
     * Determine whether the user can create a custom payment method.
     *
     * @param  User  $user  The authenticated user.
     * @return bool True when the user may create payment methods.
     */
    public function create(User $user): bool
    {
        return $user->getPermissionRouteNames()->contains('economy.me.payment-methods.store');
    }

    /**
     * Determine whether the user can update the given payment method.
     *
     * @param  User  $user  The authenticated user.
     * @param  PaymentMethod  $paymentMethod  The payment method model instance.
     * @return bool True when the method belongs to the user, or when the user is an administrator.
     */
    public function update(User $user, PaymentMethod $paymentMethod): bool
    {
        $permissions = $user->getPermissionRouteNames();

        if ($permissions->contains('admin.payment-methods.update')) {
            return true;
        }

        return ! $paymentMethod->isGlobal()
            && $paymentMethod->user_id === $user->id
            && $permissions->contains('economy.me.payment-methods.update');
    }

    /**
     * Determine whether the user can delete the given payment method.
     *
     * @param  User  $user  The authenticated user.
     * @param  PaymentMethod  $paymentMethod  The payment method model instance.
     * @return bool True when the method belongs to the user, or when the user is an administrator.
     */
    public function delete(User $user, PaymentMethod $paymentMethod): bool
    {
        $permissions = $user->getPermissionRouteNames();

        if ($permissions->contains('admin.payment-methods.destroy')) {
            return true;
        }

        return ! $paymentMethod->isGlobal()
            && $paymentMethod->user_id === $user->id
            && $permissions->contains('economy.me.payment-methods.destroy');
    }
}
