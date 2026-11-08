<?php

namespace App\Http\Requests\Admin;

use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates the deletion of an account.
 *
 * `alsoBlock` is the difference between the two deletion decisions the spec
 * requires: deleting alone leaves the identity free to register again, while
 * deleting with a block forbids it until an administrator releases the block.
 * Because the block is a persistent decision, the two paths are different
 * authorities: a plain delete needs `admin.users.delete`, and a delete-and-block
 * needs `admin.users.delete-and-block`. A caller that sends `alsoBlock: true`
 * without the stronger permission is refused before anything is written.
 *
 * Precognition reuses these exact rules and writes nothing.
 */
class DeleteUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        $actor = $this->user();

        if (! $actor instanceof User || ! $actor->canAccessApplication()) {
            return false;
        }

        $permission = $this->alsoBlocks()
            ? 'admin.users.delete-and-block'
            : 'admin.users.delete';

        return $actor->getPermissionRouteNames()->contains($permission);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'alsoBlock' => ['sometimes', 'boolean'],
            'reason' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * Determine whether this request also blocks the identity.
     *
     * Either the flag is set, or the request arrived on the dedicated
     * delete-and-block route. A plain delete route with the flag set still
     * requires the stronger permission, so the flag cannot be used to escape
     * the check.
     *
     * @return bool True when the identity must be blocked as well.
     */
    public function alsoBlocks(): bool
    {
        return $this->boolean('alsoBlock') || $this->routeIs('admin.users.delete-and-block');
    }
}
