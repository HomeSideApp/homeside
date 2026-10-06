<?php

namespace App\Http\Requests;

use App\Models\ContactSource;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class PreviewContactSourceRequest extends FormRequest
{
    public function authorize(): bool
    {
        $sourceId = $this->input('source_id');
        if (is_string($sourceId) && Str::isUuid($sourceId)) {
            return $this->user()?->can('update', ContactSource::query()->whereKey($sourceId)->firstOrFail()) ?? false;
        }

        return $this->user()?->can('create', ContactSource::class) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'provider' => ['required', Rule::in(['carddav'])],
            'source_id' => ['nullable', 'uuid'],
            'server_url' => ['required', 'url:http,https', 'max:2048'],
            'username' => ['nullable', 'string', 'max:255'],
            'app_password' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /** @return array{username: string, password: string} */
    public function credentials(): array
    {
        $sourceId = $this->validated('source_id');
        $source = $sourceId ? ContactSource::query()->whereKey($sourceId)->firstOrFail() : null;
        $existing = $source->encrypted_credentials ?? [];

        return [
            'username' => $this->validated('username') ?? $existing['username'] ?? '',
            'password' => $this->validated('app_password') ?? $existing['password'] ?? '',
        ];
    }

    protected function passedValidation(): void
    {
        $credentials = $this->credentials();
        if ($credentials['username'] === '' || $credentials['password'] === '') {
            throw new HttpResponseException(response()->json(['message' => __('app.errors.contact_source_credentials_required')], 422));
        }
    }
}
