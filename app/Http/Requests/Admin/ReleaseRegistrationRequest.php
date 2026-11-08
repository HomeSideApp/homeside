<?php

namespace App\Http\Requests\Admin;

use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates a release decision: a released rejection or a released block.
 *
 * The two releases are separate authorities, so the required permission is
 * resolved from the route rather than assumed: releasing a rejection needs
 * `admin.registrations.release-rejection`, releasing a block needs
 * `admin.registration-blocks.release`. An optional note records why the
 * prohibition was lifted.
 *
 * Precognition reuses these exact rules and writes nothing.
 */
class ReleaseRegistrationRequest extends FormRequest
{
    public function authorize(): bool
    {
        $actor = $this->user();

        if (! $actor instanceof User || ! $actor->canAccessApplication()) {
            return false;
        }

        $permission = $this->routeIs('admin.registration-blocks.release')
            ? 'admin.registration-blocks.release'
            : 'admin.registrations.release-rejection';

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
