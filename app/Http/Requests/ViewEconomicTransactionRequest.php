<?php

namespace App\Http\Requests;

use App\Concerns\AuthorizesEconomicRoute;
use Illuminate\Foundation\Http\FormRequest;

class ViewEconomicTransactionRequest extends FormRequest
{
    use AuthorizesEconomicRoute;

    public function authorize(): bool
    {
        $resource = $this->economicTransaction();

        return $this->user()?->can('view', $resource) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [];
    }
}
