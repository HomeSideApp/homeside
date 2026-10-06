<?php

namespace App\Http\Requests;

use App\Models\Household;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class RecentProductsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function passedValidation(): void
    {
        $householdId = $this->validated('household_id');
        if ($householdId !== null) {
            Gate::authorize('view', Household::query()->findOrFail($householdId));
        }
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'household_id' => ['sometimes', 'uuid', 'exists:households,id'],
            'limit' => ['sometimes', 'integer', 'between:1,50'],
        ];
    }
}
