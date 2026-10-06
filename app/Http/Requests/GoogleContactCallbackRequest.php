<?php

namespace App\Http\Requests;

use App\Models\ContactSource;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Laravel\Socialite\Two\User;

class GoogleContactCallbackRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [];
    }

    public function validateIdentity(User $google): void
    {
        abort_unless((string) $google->getId() !== ''
            && (string) $google->getEmail() !== ''
            && ($google->user['email_verified'] ?? false) === true, 422);
    }

    public function authorizeReconnection(ContactSource $source, string $sub): void
    {
        Gate::authorize('update', $source);
        abort_unless($source->provider === 'google'
            && $source->user_id === $this->user()?->id
            && ($source->provider_configuration['google_sub'] ?? null) === $sub, 403);
    }

    public function validateRefreshToken(?string $refreshToken): void
    {
        abort_unless(is_string($refreshToken) && trim($refreshToken) !== '', 422);
    }

    public function authorizeNewConnection(string $sub): void
    {
        Gate::authorize('create', ContactSource::class);
        if (ContactSource::query()->where('provider', 'google')->where('user_id', $this->user()?->id)
            ->where('provider_configuration->google_sub', $sub)->exists()) {
            throw ValidationException::withMessages(['google' => __('app.errors.google_account_already_connected')]);
        }
    }
}
