<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class IndexAdminRolesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->getPermissionRouteNames()->contains('admin.roles') ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'search' => ['sometimes', 'string', 'max:100'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'perPage' => ['sometimes', 'integer', 'between:1,100'],
            'sort' => ['sometimes', 'in:name,created_at,updated_at'],
            'direction' => ['sometimes', 'in:asc,desc'],
        ];
    }
}
