<?php

namespace App\Http\Requests\Admin;

use App\Concerns\UserValidationRules;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateUserRequest extends FormRequest
{
    use UserValidationRules;

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $user = $this->route('user');

        return $this->userUpdateRules($user instanceof User ? $user->id : '');
    }
}
