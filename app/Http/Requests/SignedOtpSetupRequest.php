<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

class SignedOtpSetupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->setupUser()->canAccessApplication();
    }

    public function setupUser(): User
    {
        $user = $this->route('user');
        abort_unless($user instanceof User, 404);

        return $user;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [];
    }
}
