<?php

namespace App\Http\Requests\Admin;

use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates the rejection of a registration.
 *
 * The reason is REQUIRED: a rejection also creates a persistent identity block,
 * so the operator must record why the decision was made. The reason is stored
 * on the block (never exposed to the rejected person) and is what the Rejected
 * tab shows when an administrator later reviews the decision.
 *
 * Precognition reuses these exact rules: a precognitive request is validated
 * and answered before the controller runs, so it writes nothing.
 */
class RejectRegistrationRequest extends FormRequest
{
    public function authorize(): bool
    {
        $actor = $this->user();

        return $actor instanceof User
            && $actor->canAccessApplication()
            && $actor->getPermissionRouteNames()->contains('admin.registrations.reject');
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'min:3', 'max:500'],
        ];
    }
}
