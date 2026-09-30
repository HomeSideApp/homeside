<?php

namespace App\Http\Requests;

use App\Concerns\ListValidationRules;
use Illuminate\Foundation\Http\FormRequest;

class StoreListRequest extends FormRequest
{
    use ListValidationRules;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return $this->listRules();
    }
}
