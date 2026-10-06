<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexAdminAiUsageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->getPermissionRouteNames()->contains('admin.ai-providers') ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'agent' => ['sometimes', 'string', 'max:255'],
            'provider_name' => ['sometimes', 'string', 'max:255'],
            'model_name' => ['sometimes', 'string', 'max:255'],
            'status' => ['sometimes', Rule::in(['queued', 'running', 'ok', 'success', 'error', 'cancelled'])],
            'date_from' => ['sometimes', 'date_format:Y-m-d'],
            'date_to' => ['sometimes', 'date_format:Y-m-d', 'after_or_equal:date_from'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'perPage' => ['sometimes', 'integer', 'between:1,100'],
        ];
    }
}
