<?php

namespace App\Http\Requests;

use App\Concerns\AuthorizesShoppingListRoute;
use Illuminate\Foundation\Http\FormRequest;

class QuickCreateProductRequest extends FormRequest
{
    use AuthorizesShoppingListRoute;

    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && ($this->route('list') === null || $user->can('update', $this->shoppingList()));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
        ];
    }
}
