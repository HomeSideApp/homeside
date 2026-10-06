<?php

namespace App\Http\Requests;

class IndexEconomicTransactionsRequest extends IndexPersonalEconomicTransactionsRequest
{
    public function authorize(): bool
    {
        abort_unless($this->economicHousehold() !== null, 404);

        return parent::authorize();
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [...parent::rules(), 'scope' => ['sometimes', 'in:personal,shared'], 'created_by' => ['sometimes', 'uuid']];
    }

    protected function passedValidation(): void
    {
        $creator = $this->validated('created_by');
        if ($creator !== null) {
            abort_unless(($this->economicHousehold()?->members()->where('user_id', $creator)->exists() ?? false), 404);
        }
    }
}
