<?php

namespace App\Http\Requests;

use App\Models\ContactSource;
use Illuminate\Foundation\Http\FormRequest;

class ManageGoogleContactSourceRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var ContactSource $source */
        $source = $this->route('source');
        $user = $this->user();
        if ($user === null || ! $user->can('update', $source)) {
            return false;
        }
        abort_unless($source->provider === 'google' && $source->user_id === $user->id, 404);

        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [];
    }
}
