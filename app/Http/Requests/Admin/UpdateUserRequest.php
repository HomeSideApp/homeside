<?php

namespace App\Http\Requests\Admin;

use App\Concerns\UserValidationRules;
use App\Models\User;
use App\Support\PlatformAccess;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateUserRequest extends FormRequest
{
    use UserValidationRules;

    public function authorize(): bool
    {
        $actor = $this->user();
        if ($actor === null) {
            return false;
        }
        if ($this->hasAny(['role', 'roles', 'can_create_households_override'])) {
            return app(PlatformAccess::class)->canManage($actor);
        }

        return $actor->getPermissionRouteNames()->contains('admin.users.update');
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $user = $this->route('user');

        return $this->userUpdateRules($user instanceof User ? $user->id : '');
    }
}
