<?php

namespace App\Http\Requests;

use App\Concerns\ListValidationRules;
use App\Support\HouseholdAccess;
use Illuminate\Foundation\Http\FormRequest;

class StoreListRequest extends FormRequest
{
    use ListValidationRules;

    public function authorize(): bool
    {
        $user = $this->user();
        $household = $this->route('household');

        return $user !== null && ($household === null || app(HouseholdAccess::class)->allows($user, $household, 'shopping_lists', true));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            ...$this->listRules(),
            // An offline client mints the id locally so the queued row keeps the
            // same identity once it reaches the server; HasUuids only fills an
            // empty key, so the validated id travels straight to the insert.
            'id' => ['sometimes', 'uuid'],
        ];
    }
}
