<?php

namespace App\Actions\Contacts;

use App\Data\Contacts\ContactData;
use App\Models\Contact;
use App\Models\User;
use App\Services\Contacts\ContactPhotoProcessor;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class SaveLocalContact
{
    public function __construct(private ContactPhotoProcessor $photos) {}

    public function execute(
        ContactData $data,
        User $user,
        ?Contact $contact = null,
        ?UploadedFile $photo = null,
        bool $removePhoto = false,
    ): Contact {
        if ($contact !== null && $contact->household_id !== $data->householdId) {
            throw ValidationException::withMessages(['household_id' => 'Contact ownership cannot be changed.']);
        }

        return DB::transaction(function () use ($data, $user, $contact, $photo, $removePhoto): Contact {
            if ($contact === null) {
                $contact = Contact::create([
                    'user_id' => $data->householdId === null ? $user->id : null,
                    'household_id' => $data->householdId,
                    'type' => $data->type,
                    'display_name' => $data->displayName,
                ]);
            } else {
                $contact->update(['type' => $data->type, 'display_name' => $data->displayName]);
            }

            $record = $contact->records()->whereNull('contact_source_id')->orderBy('created_at')->orderBy('id')->first();
            if ($record === null) {
                $record = $contact->records()->create(['formatted_name' => $data->displayName]);
            }

            $record->update([
                'formatted_name' => $data->displayName,
                'given_name' => $data->givenName,
                'family_name' => $data->familyName,
                'additional_name' => $data->additionalName,
                'nickname' => $data->nickname,
                'organization' => $data->organization,
                'job_title' => $data->jobTitle,
                'birthday' => $data->birthday,
                'notes' => $data->notes,
            ]);

            $contact->update(['preferred_record_id' => $record->id]);

            foreach (['emails', 'phones', 'addresses', 'urls'] as $kind) {
                DB::table('contact_'.$kind)->where('contact_record_id', $record->id)->delete();

                foreach ($data->{$kind} as $value) {
                    DB::table('contact_'.$kind)->insert([
                        'contact_record_id' => $record->id,
                        'value' => $value->value,
                        'type' => $value->type,
                        'preferred' => $value->preferred,
                    ]);
                }
            }

            DB::table('contact_record_dates')->where('contact_record_id', $record->id)->delete();
            foreach ($data->dates as $date) {
                DB::table('contact_record_dates')->insert([
                    'contact_record_id' => $record->id,
                    'kind' => $date['kind'],
                    'label' => $date['label'],
                    'value' => $date['value'],
                    'value_type' => 'date',
                ]);
            }

            DB::table('contact_record_relations')->where('contact_record_id', $record->id)->delete();
            foreach ($data->relations as $index => $relation) {
                $targetId = $relation['related_contact_id'];
                $target = $targetId === null ? null : Contact::query()->visibleTo($user)->find($targetId);
                if ($targetId !== null && ($target === null || $target->id === $contact->id
                    || ($contact->household_id !== null && $target->household_id !== $contact->household_id))) {
                    throw ValidationException::withMessages(['relations.'.$index.'.related_contact_id' => 'El contacto relacionado no está disponible para esta libreta.']);
                }
                if ($target === null && blank($relation['name'])) {
                    throw ValidationException::withMessages(['relations.'.$index.'.name' => 'Indica un contacto o un nombre para la relación.']);
                }

                DB::table('contact_record_relations')->insert([
                    'contact_record_id' => $record->id,
                    'type' => $relation['type'],
                    'related_contact_id' => $target?->id,
                    'name' => $target?->display_name ?? $relation['name'],
                ]);
            }

            if ($photo !== null) {
                $bytes = $photo->get();
                if (! is_string($bytes)) {
                    throw ValidationException::withMessages(['photo' => 'No se pudo leer la fotografía.']);
                }
                $this->photos->store($record, $bytes);
            } elseif ($removePhoto) {
                $this->photos->remove($record);
            }

            return $contact->load('records');
        });
    }
}
