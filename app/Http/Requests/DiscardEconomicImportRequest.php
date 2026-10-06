<?php

namespace App\Http\Requests;

use App\Concerns\AuthorizesEconomicRoute;
use Illuminate\Foundation\Http\FormRequest;

class DiscardEconomicImportRequest extends FormRequest
{
    use AuthorizesEconomicRoute;

    public function authorize(): bool
    {
        $resource = $this->economicImport();

        return $this->user()?->can('discard', $resource) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [];
    }
}
