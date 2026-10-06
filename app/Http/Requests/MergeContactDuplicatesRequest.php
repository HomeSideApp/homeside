<?php

namespace App\Http\Requests;

use App\Models\Contact;

class MergeContactDuplicatesRequest extends IndexContactsRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'groups' => ['required', 'array', 'min:1', 'max:100'],
            'groups.*.primary_id' => ['required', 'uuid'],
            'groups.*.duplicate_ids' => ['required', 'array', 'min:1', 'max:100'],
            'groups.*.duplicate_ids.*' => ['required', 'uuid', 'distinct'],
        ];
    }

    protected function passedValidation(): void
    {
        $user = $this->user();
        abort_unless($user !== null, 403);
        foreach ($this->validated('groups') as $group) {
            foreach ([$group['primary_id'], ...$group['duplicate_ids']] as $id) {
                $contact = Contact::query()->visibleTo($user)->whereKey($id)->firstOrFail();
                abort_unless($user->can('update', $contact), 403);
            }
        }
    }
}
