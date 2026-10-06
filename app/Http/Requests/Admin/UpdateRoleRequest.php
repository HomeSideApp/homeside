<?php

namespace App\Http\Requests\Admin;

use App\Concerns\RoleValidationRules;
use App\Models\Role;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateRoleRequest extends FormRequest
{
    use RoleValidationRules;

    public function authorize(): bool
    {
        $allowed = $this->user()?->getPermissionRouteNames()->contains('admin.roles.update') ?? false;
        if ($allowed) {
            /** @var Role $role */
            $role = $this->route('role');
            abort_if($role->isSystem(), 403, __('app.errors.system_role_cannot_update'));
        }

        return $allowed;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $role = $this->route('role');

        return $this->roleRules($role instanceof Role ? $role->id : null);
    }
}
