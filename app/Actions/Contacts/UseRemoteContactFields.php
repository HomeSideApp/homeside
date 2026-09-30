<?php

namespace App\Actions\Contacts;

use App\Models\Contact;
use App\Models\ContactRecord;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class UseRemoteContactFields
{
    public const FIELDS = [
        'formatted_name', 'given_name', 'family_name', 'additional_name',
        'nickname', 'organization', 'job_title', 'birthday', 'notes',
        'emails', 'phones', 'addresses', 'urls', 'dates', 'relations', 'photo',
    ];

    /**
     * @param  list<string>  $fields
     */
    public function execute(Contact $contact, ContactRecord $remote, array $fields): void
    {
        if ($remote->contact_id !== $contact->id || $remote->contact_source_id === null || $remote->remote_deleted_at !== null) {
            throw ValidationException::withMessages(['record_id' => 'La versión remota no pertenece a este contacto.']);
        }

        DB::transaction(function () use ($contact, $remote, $fields): void {
            $local = $contact->records()->whereNull('contact_source_id')
                ->orderBy('created_at')->orderBy('id')->first();
            if ($local === null) {
                throw ValidationException::withMessages(['record_id' => 'Edita primero el contacto para crear una versión local.']);
            }

            $scalars = array_intersect($fields, [
                'formatted_name', 'given_name', 'family_name', 'additional_name',
                'nickname', 'organization', 'job_title', 'birthday', 'notes',
            ]);
            if ($scalars !== []) {
                $updates = [];
                foreach ($scalars as $field) {
                    $updates[$field] = $field === 'birthday'
                        ? $remote->birthday?->toDateString()
                        : $remote->{$field};
                }
                $local->update($updates);
            }

            foreach (array_intersect($fields, ['emails', 'phones', 'addresses', 'urls']) as $kind) {
                $table = 'contact_'.$kind;
                DB::table($table)->where('contact_record_id', $local->id)->delete();
                foreach (DB::table($table)->where('contact_record_id', $remote->id)->get(['value', 'type', 'preferred']) as $row) {
                    DB::table($table)->insert([
                        'contact_record_id' => $local->id,
                        'value' => $row->value,
                        'type' => $row->type,
                        'preferred' => $row->preferred,
                    ]);
                }
            }

            foreach (array_intersect($fields, ['dates', 'relations']) as $kind) {
                $table = 'contact_record_'.$kind;
                $columns = $kind === 'dates'
                    ? ['kind', 'label', 'value', 'value_type']
                    : ['type', 'related_contact_id', 'name', 'external_value'];
                DB::table($table)->where('contact_record_id', $local->id)->delete();
                foreach (DB::table($table)->where('contact_record_id', $remote->id)->get($columns) as $row) {
                    DB::table($table)->insert(['contact_record_id' => $local->id, ...get_object_vars($row)]);
                }
            }

            $photoRecordId = null;
            if (in_array('photo', $fields, true)) {
                $photoRecordId = DB::table('contact_record_photos')
                    ->where('contact_record_id', $remote->id)->value('contact_record_id');
                if ($photoRecordId === null) {
                    throw ValidationException::withMessages(['fields' => 'Esta fuente no tiene fotografía.']);
                }
            }

            $contact->update([
                'preferred_record_id' => $local->id,
                'preferred_photo_record_id' => $photoRecordId ?? $contact->preferred_photo_record_id,
                'display_name' => in_array('formatted_name', $fields, true)
                    ? $remote->formatted_name : $contact->display_name,
            ]);
        });
    }
}
