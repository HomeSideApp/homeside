<?php

namespace App\Http\Requests;

use App\Models\Household;
use Illuminate\Foundation\Http\FormRequest;

class TransferHouseholdOwnershipRequest extends FormRequest
{
    public function authorize(): bool
    {
        $household = $this->route('household');

        return $household instanceof Household && $this->user() !== null && $this->user()->id === $household->owner_id;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['member_id' => ['required', 'uuid']];
    }
}
