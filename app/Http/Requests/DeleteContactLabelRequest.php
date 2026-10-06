<?php

namespace App\Http\Requests;

use App\Models\Contact;
use App\Models\ContactLabel;
use Illuminate\Foundation\Http\FormRequest;

class DeleteContactLabelRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        if ($user === null || ! $user->can('create', Contact::class)) {
            return false;
        }

        /** @var ContactLabel $label */
        $label = $this->route('label');
        abort_unless($label->user_id === $user->id, 404);

        return ! $label->contacts()->wherePivot('imported', true)->exists();
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [];
    }
}
