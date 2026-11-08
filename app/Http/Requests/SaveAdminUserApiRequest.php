<?php

namespace App\Http\Requests;

use App\Enums\AppLocale;
use App\Models\User;
use App\Support\PlatformAccess;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveAdminUserApiRequest extends FormRequest
{
    public function authorize(): bool
    {
        $actor = $this->user();
        if ($actor === null) {
            return false;
        }
        if ($this->route('user') === null || $this->hasAny(['role', 'roles', 'can_create_households_override'])) {
            return app(PlatformAccess::class)->canManage($actor);
        }

        return $actor->getPermissionRouteNames()->contains('admin.users.update');
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        /** @var User|null $user */
        $user = $this->route('user');
        $presence = $user === null ? 'required' : 'sometimes';

        return [
            'name' => [$presence, 'string', 'max:255'],
            'email' => [$presence, 'email', 'max:255', Rule::unique('users', 'email')->ignore($user?->id)],
            'locale' => [$presence, Rule::enum(AppLocale::class)],
            'role' => ['sometimes', 'string', 'exists:roles,slug'],
            // Tri-state: null delegates to Policy + entitlement, false is an
            // explicit prohibition. `true` is deliberately NOT a valid state,
            // so it must be rejected here rather than silently stored.
            'can_create_households_override' => ['sometimes', 'nullable', 'boolean', Rule::in(['0', ''])],
            'roles' => [$user === null ? 'required_without:role' : 'sometimes', 'array', 'min:1'],
            'roles.*' => ['string', 'distinct', 'exists:roles,slug'],
        ];
    }
}
