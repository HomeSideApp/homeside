<?php

namespace App\Http\Controllers\Web;

use App\Actions\Economy\CreatePaymentMethod;
use App\Actions\Economy\DeletePaymentMethod;
use App\Actions\Economy\UpdatePaymentMethod;
use App\Data\Economy\PaymentMethodData;
use App\Http\Controllers\Controller;
use App\Http\Requests\StorePaymentMethodRequest;
use App\Http\Requests\UpdatePaymentMethodRequest;
use App\Http\Resources\Economy\PaymentMethodResource;
use App\Models\PaymentMethod;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Class PaymentMethodController
 *
 * Handles the user payment method catalogue. Global entries are read-only here, while custom
 * methods can be created, updated and deleted by their owner.
 */
final class PaymentMethodController extends Controller
{
    /**
     * List the payment methods available to the authenticated user.
     *
     * @param  Request  $request  The incoming HTTP request.
     * @return Response The Inertia payment method list response.
     */
    public function index(Request $request): Response
    {
        $user = $this->authenticatedUser($request);
        $this->authorize('viewAny', PaymentMethod::class);

        return Inertia::render('economy/PaymentMethods/Index', [
            'paymentMethods' => PaymentMethodResource::collection(
                PaymentMethod::query()
                    ->visibleTo($user->id)
                    ->withCount('accounts')
                    ->orderBy('sort_order')
                    ->orderBy('name')
                    ->get()
            )->resolve($request),
        ]);
    }

    /**
     * Persist a new custom payment method for the authenticated user.
     *
     * @param  StorePaymentMethodRequest  $request  The authorized and validated payment method request.
     * @param  CreatePaymentMethod  $action  The action used to persist the payment method.
     * @return RedirectResponse The redirect response back to the payment method list.
     */
    public function store(StorePaymentMethodRequest $request, CreatePaymentMethod $action): RedirectResponse
    {
        $data = PaymentMethodData::fromArray($request->validated());
        $action->execute($data, $this->authenticatedUser($request));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('app.toast.payment_method_created')]);

        return to_route('economy.me.payment-methods.index');
    }

    /**
     * Apply the validated changes to a custom payment method.
     *
     * @param  UpdatePaymentMethodRequest  $request  The authorized and validated update request.
     * @param  PaymentMethod  $paymentMethod  The routed payment method model instance.
     * @param  UpdatePaymentMethod  $action  The action used to persist the update.
     * @return RedirectResponse The redirect response back to the payment method list.
     */
    public function update(
        UpdatePaymentMethodRequest $request,
        PaymentMethod $paymentMethod,
        UpdatePaymentMethod $action,
    ): RedirectResponse {
        $data = PaymentMethodData::fromArray($request->validated());
        $allowGlobal = $this->authenticatedUser($request)
            ->getPermissionRouteNames()
            ->contains('admin.payment-methods.update');
        $action->execute($paymentMethod, $data, $allowGlobal);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('app.toast.payment_method_updated')]);

        return to_route('economy.me.payment-methods.index');
    }

    /**
     * Delete a custom payment method.
     *
     * @param  Request  $request  The incoming HTTP request.
     * @param  PaymentMethod  $paymentMethod  The routed payment method model instance.
     * @param  DeletePaymentMethod  $action  The action used to delete the payment method.
     * @return RedirectResponse The redirect response back to the payment method list.
     */
    public function destroy(
        Request $request,
        PaymentMethod $paymentMethod,
        DeletePaymentMethod $action,
    ): RedirectResponse {
        $this->authorize('delete', $paymentMethod);
        $allowGlobal = $this->authenticatedUser($request)
            ->getPermissionRouteNames()
            ->contains('admin.payment-methods.destroy');
        $action->execute($paymentMethod, $allowGlobal);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('app.toast.payment_method_deleted')]);

        return to_route('economy.me.payment-methods.index');
    }
}
