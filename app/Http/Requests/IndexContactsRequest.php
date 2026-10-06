<?php

namespace App\Http\Requests;

use App\Models\Contact;
use App\Models\Household;
use Illuminate\Foundation\Http\FormRequest;

class IndexContactsRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        /** @var Household|null $household */
        $household = $this->route('household');

        return $user !== null
            && $user->can('viewAny', Contact::class)
            && ($household === null || $household->isMember($user));
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [];
    }
}
