<?php

namespace App\Http\Requests\Admin;

use App\Concerns\UserValidationRules;
use App\Support\PlatformAccess;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreUserRequest extends FormRequest
{
    use UserValidationRules;

    public function authorize(): bool
    {
        $actor = $this->user();

        return $actor !== null && app(PlatformAccess::class)->canManage($actor);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return $this->userCreateRules();
    }
}
