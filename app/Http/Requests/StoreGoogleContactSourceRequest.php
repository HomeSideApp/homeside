<?php

namespace App\Http\Requests;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;

class StoreGoogleContactSourceRequest extends CreateContactSourceRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'favorite_label' => ['required', 'string', 'max:80'],
            'selected_collections' => ['required', 'array', 'size:1'],
            'selected_collections.0' => ['required', 'in:all'],
        ];
    }

    /** @return array<string, mixed> */
    public function draft(): array
    {
        $key = $this->session()->get('google.contact_draft');
        $stored = is_string($key) ? Cache::get('google-contact-draft:'.$key) : null;
        $draft = is_string($stored) ? json_decode(Crypt::decryptString($stored), true) : null;
        abort_unless(is_array($draft) && $draft['user_id'] === $this->user()?->id, 422, __('app.errors.google_connection_expired'));

        return $draft;
    }

    protected function passedValidation(): void
    {
        $this->draft();
    }
}
