<?php

namespace App\Http\Requests;

use App\Support\PlatformAccess;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ApproveGoogleApprovalRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && app(PlatformAccess::class)->canManage($user);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['role' => ['required', 'string', Rule::exists('roles', 'slug')]];
    }
}
