<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class IndexAdminUsersRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->getPermissionRouteNames()->contains('admin.users') ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'search' => ['sometimes', 'string', 'max:100'],
            'role' => ['sometimes', 'string', 'exists:roles,slug'],
            'approval' => ['sometimes', 'in:pending,approved,rejected'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'perPage' => ['sometimes', 'integer', 'between:1,100'],
            'sort' => ['sometimes', 'in:name,email,created_at,updated_at'],
            'direction' => ['sometimes', 'in:asc,desc'],
        ];
    }
}
