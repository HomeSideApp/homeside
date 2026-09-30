<?php

namespace App\Http\Requests;

use App\Enums\PaymentMethodKind;
use App\Models\PaymentMethod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePaymentMethodRequest extends FormRequest
{
    /**
     * Determine whether the authenticated user may update the routed payment method.
     *
     * @return bool True when the method belongs to the user, or the user is an administrator.
     */
    public function authorize(): bool
    {
        $paymentMethod = $this->route('paymentMethod');

        if (! $paymentMethod instanceof PaymentMethod) {
            return false;
        }

        return $this->user()?->can('update', $paymentMethod) ?? false;
    }

    /**
     * Define the validation rules for updating a custom payment method.
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
