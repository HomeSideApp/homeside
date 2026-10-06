<?php

namespace App\Http\Requests;

use App\Concerns\AuthorizesEconomicRoute;
use Illuminate\Foundation\Http\FormRequest;

class DeleteEconomicTransactionRequest extends FormRequest
{
    use AuthorizesEconomicRoute;

    public function authorize(): bool
    {
        $resource = $this->economicTransaction();

        return $this->user()?->can('delete', $resource) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [];
    }
}
