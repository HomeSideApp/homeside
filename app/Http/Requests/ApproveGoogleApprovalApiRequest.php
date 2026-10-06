<?php

namespace App\Http\Requests;

class ApproveGoogleApprovalApiRequest extends ApproveGoogleApprovalRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => ['required', 'string', 'distinct', 'exists:roles,slug'],
        ];
    }
}
