<?php

namespace App\Http\Requests;

use App\Models\PaymentMethod;
use Illuminate\Foundation\Http\FormRequest;

class IndexPaymentMethodsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', PaymentMethod::class) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [];
    }
}
