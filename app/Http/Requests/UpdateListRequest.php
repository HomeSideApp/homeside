<?php

namespace App\Http\Requests;

use App\Concerns\AuthorizesShoppingListRoute;
use App\Concerns\ListValidationRules;
use Illuminate\Foundation\Http\FormRequest;

class UpdateListRequest extends FormRequest
{
    use AuthorizesShoppingListRoute;
    use ListValidationRules;

    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->shoppingList()) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return $this->listRules();
    }
}
