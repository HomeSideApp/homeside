<?php

namespace App\Http\Requests;

use App\Models\ContactSource;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveContactSourceRequest extends FormRequest
{
    public function authorize(): bool
    {
        $source = $this->route('source');
        if ($source instanceof ContactSource) {
            return $this->user()?->can('update', $source) ?? false;
        }

        if (! ($this->user()?->can('create', ContactSource::class) ?? false)) {
            return false;
        }

        $householdId = $this->input('household_id');

        return $householdId === null || $this->user()->households()->whereKey($householdId)->exists();
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'household_id' => [$this->route('source') instanceof ContactSource ? 'prohibited' : 'nullable', 'uuid', Rule::exists('households', 'id')],
            'name' => ['required', 'string', 'max:255'],
            'server_url' => ['required', 'url:http,https', 'max:2048'],
            'favorite_label' => ['sometimes', 'required', 'string', 'max:80'],
            'username' => [$this->route('source') instanceof ContactSource ? 'nullable' : 'required', 'string', 'max:255'],
            'app_password' => [$this->route('source') instanceof ContactSource ? 'nullable' : 'required', 'string', 'max:1000'],
            'enabled' => ['sometimes', 'boolean'],
            'sync_enabled' => ['sometimes', 'boolean'],
            'selected_collections' => ['required', 'array', 'min:1', 'max:100'],
            'selected_collections.*' => ['required', 'string', 'max:2048', 'distinct'],
        ];
    }
}
