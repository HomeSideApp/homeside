<?php

namespace App\Http\Requests\Admin;

use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates an account-restriction decision (suspend, unsuspend, block, unblock).
 *
 * The four decisions share one request because they share one shape, but they
 * do NOT share one authority: each route is guarded by its own permission and
 * the request resolves the required permission from the route. A block accepts
 * a reason because it persists an identity block; a suspension is a state of
 * the account row and carries no persistent note, so its reason is ignored.
 *
 * Precognition reuses these exact rules and writes nothing.
 */
class SuspendUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        $actor = $this->user();

        if (! $actor instanceof User || ! $actor->canAccessApplication()) {
            return false;
        }

        $permission = match (true) {
            $this->routeIs('admin.users.block') => 'admin.users.block',
            $this->routeIs('admin.users.unblock') => 'admin.users.unblock',
            default => 'admin.users.suspend',
        };

        return $actor->getPermissionRouteNames()->contains($permission);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'reason' => ['nullable', 'string', 'max:500'],
        ];
    }
}
