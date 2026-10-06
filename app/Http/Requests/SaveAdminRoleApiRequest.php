<?php

namespace App\Http\Requests;

use App\Models\Role;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveAdminRoleApiRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Role|null $role */
        $role = $this->route('role');
        $allowed = $this->user()?->getPermissionRouteNames()->contains($role === null ? 'admin.roles.store' : 'admin.roles.update') ?? false;
        if ($allowed && $role !== null) {
            abort_if($role->isSystem(), 409, __('app.errors.system_role_cannot_update'));
        }

        return $allowed;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        /** @var Role|null $role */
        $role = $this->route('role');
        $presence = $role === null ? 'required' : 'sometimes';

        return [
            'name' => [$presence, 'string', 'max:255', Rule::unique('roles', 'name')->ignore($role?->id)],
            'permissions' => [$presence, 'array'],
            'permissions.*' => ['string', 'distinct', 'exists:permissions,route_name'],
        ];
    }
}
