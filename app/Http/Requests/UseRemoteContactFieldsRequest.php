<?php

namespace App\Http\Requests;

use App\Actions\Contacts\UseRemoteContactFields;
use App\Models\Contact;
use App\Models\ContactRecord;
use Illuminate\Validation\Rule;

class UseRemoteContactFieldsRequest extends ManageContactRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'record_id' => ['required', 'uuid'],
            'fields' => ['required', 'array', 'min:1', 'max:16'],
            'fields.*' => ['required', 'string', 'distinct', Rule::in(UseRemoteContactFields::FIELDS)],
        ];
    }

    protected function passedValidation(): void
    {
        /** @var Contact $contact */
        $contact = $this->route('contact');
        ContactRecord::query()->where('contact_id', $contact->id)
            ->whereNotNull('contact_source_id')->whereKey($this->validated('record_id'))->firstOrFail();
    }
}
