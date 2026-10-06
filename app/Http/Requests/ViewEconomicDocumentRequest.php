<?php

namespace App\Http\Requests;

use App\Concerns\AuthorizesEconomicRoute;
use Illuminate\Foundation\Http\FormRequest;

class ViewEconomicDocumentRequest extends FormRequest
{
    use AuthorizesEconomicRoute;

    public function authorize(): bool
    {
        $resource = $this->economicDocument();

        return $this->user()?->can('view', $resource) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [];
    }
}
