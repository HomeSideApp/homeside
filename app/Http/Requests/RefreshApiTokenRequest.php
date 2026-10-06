<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RefreshApiTokenRequest extends FormRequest
{
    public function authorize(): bool
    {
        abort_unless($this->bearerToken() !== null, 401, __('app.errors.token_invalid'));

        return $this->user() !== null;
    }

    public function token(): string
    {
        return $this->bearerToken() ?? throw new \LogicException('The refresh token has not been authorized.');
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [];
    }
}
