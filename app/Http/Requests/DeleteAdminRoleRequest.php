<?php

namespace App\Http\Requests;

use App\Models\Role;
use Illuminate\Foundation\Http\FormRequest;

class DeleteAdminRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        $allowed = $this->user()?->getPermissionRouteNames()->contains('admin.roles.destroy') ?? false;
        if ($allowed) {
            /** @var Role $role */
            $role = $this->route('role');
            if ($this->routeIs('api.v1.*')) {
                abort_if($role->isSystem(), 409, __('app.errors.system_role_cannot_delete'));
                abort_if($role->users()->exists(), 409, __('app.errors.assigned_role_cannot_delete'));
            } else {
                abort_if($role->isSystem(), 403, __('app.errors.system_role_cannot_delete'));
            }
        }

        return $allowed;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [];
    }
}
