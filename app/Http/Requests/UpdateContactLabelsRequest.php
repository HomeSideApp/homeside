<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class UpdateContactLabelsRequest extends ManageContactRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'label_ids' => ['present', 'array', 'max:100'],
            'label_ids.*' => ['uuid', 'distinct', Rule::exists('contact_labels', 'id')->where('user_id', $this->user()?->id)],
        ];
    }
}
