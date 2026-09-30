<?php

namespace App\Http\Requests;

use App\Models\Contact;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveContactRequest extends FormRequest
{
    public function authorize(): bool
    {
        $contact = $this->route('contact');

        if ($contact instanceof Contact) {
            return $this->user()?->can('update', $contact) ?? false;
        }

        if (! ($this->user()?->can('create', Contact::class) ?? false)) {
            return false;
        }

        $householdId = $this->input('household_id');

        return $householdId === null || (
            $this->user()->getPermissionRouteNames()->contains('contacts.home.create')
            && $this->user()->households()->whereKey($householdId)->exists()
        );
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        $contact = $this->route('contact');

        return [
            'household_id' => [$contact instanceof Contact ? 'prohibited' : 'nullable', 'uuid', Rule::exists('households', 'id')],
            'type' => ['required', Rule::in(['person', 'organization'])],
            'display_name' => ['required', 'string', 'max:255'],
            'given_name' => ['nullable', 'string', 'max:255'],
            'family_name' => ['nullable', 'string', 'max:255'],
            'additional_name' => ['nullable', 'string', 'max:255'],
            'nickname' => ['nullable', 'string', 'max:255'],
            'organization' => ['nullable', 'string', 'max:255'],
            'job_title' => ['nullable', 'string', 'max:255'],
            'birthday' => ['nullable', 'date_format:Y-m-d'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'photo' => ['nullable', 'image', 'mimes:jpeg,png,webp', 'max:3072'],
            'remove_photo' => ['sometimes', 'boolean'],
            'dates' => ['sometimes', 'array', 'max:20'],
            'dates.*.kind' => ['required', Rule::in(['anniversary', 'custom'])],
            'dates.*.label' => ['nullable', 'string', 'max:255'],
            'dates.*.value' => ['required', 'date_format:Y-m-d'],
            'relations' => ['sometimes', 'array', 'max:20'],
            'relations.*.type' => ['required', Rule::in(['parent', 'child', 'sibling', 'spouse', 'friend', 'colleague', 'emergency', 'other'])],
            'relations.*.related_contact_id' => ['nullable', 'uuid', Rule::exists('contacts', 'id')],
            'relations.*.name' => ['nullable', 'string', 'max:255'],
            'emails' => ['sometimes', 'array', 'max:20'],
            'emails.*.value' => ['required', 'email', 'max:320'],
            'phones' => ['sometimes', 'array', 'max:20'],
            'phones.*.value' => ['required', 'string', 'max:100'],
            'addresses' => ['sometimes', 'array', 'max:20'],
            'addresses.*.value' => ['required', 'string', 'max:1000'],
            'urls' => ['sometimes', 'array', 'max:20'],
            'urls.*.value' => ['required', 'url:http,https', 'max:2048'],
            'emails.*.type' => ['nullable', 'string', 'max:32'],
            'phones.*.type' => ['nullable', 'string', 'max:32'],
            'addresses.*.type' => ['nullable', 'string', 'max:32'],
            'urls.*.type' => ['nullable', 'string', 'max:32'],
            'emails.*.preferred' => ['sometimes', 'boolean'],
            'phones.*.preferred' => ['sometimes', 'boolean'],
            'addresses.*.preferred' => ['sometimes', 'boolean'],
            'urls.*.preferred' => ['sometimes', 'boolean'],
        ];
    }
}
