<?php

namespace App\Http\Requests;

use App\Enums\PaymentMethodKind;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePaymentMethodRequest extends FormRequest
{
    /**
     * Determine whether the authenticated user may create a custom payment method.
     *
     * @return bool True when the user is authenticated and allowed to manage payment methods.
     */
    public function authorize(): bool
    {
        return $this->user()?->getPermissionRouteNames()->contains('economy.me.payment-methods.store') ?? false;
    }

    /**
     * Define the validation rules for a new custom payment method.
     *
     * @return array<string, mixed> The Laravel validation rules keyed by request field.
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:80'],
            'kind' => ['required', Rule::enum(PaymentMethodKind::class)],
            'icon' => ['nullable', 'string', 'max:60'],
            'color' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'is_active' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0', 'max:9999'],
        ];
    }

    /**
     * Define the translated attribute names used in validation messages.
     *
     * @return array<string, string> The attribute labels keyed by request field.
     */
    public function attributes(): array
    {
        return [
            'name' => __('app.economy.payment_method.name'),
            'kind' => __('app.economy.payment_method.kind'),
            'color' => __('app.economy.payment_method.color'),
        ];
    }
}
