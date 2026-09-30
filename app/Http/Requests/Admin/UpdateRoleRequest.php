<?php

namespace App\Http\Requests\Admin;

use App\Concerns\RoleValidationRules;
use App\Models\Role;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateRoleRequest extends FormRequest
{
    use RoleValidationRules;

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $role = $this->route('role');

        return $this->roleRules($role instanceof Role ? $role->id : null);
    }
}
