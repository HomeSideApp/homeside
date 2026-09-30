<?php

namespace App\Actions\Contacts;

use App\Models\Contact;
use App\Models\ContactRecord;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class MergeContacts
{
    public function __construct(private FindContactDuplicates $duplicates) {}

    /**
     * @param  list<array{primary_id: string, duplicate_ids: list<string>}>  $groups
     */
    public function execute(User $user, array $groups): int
    {
        return DB::transaction(function () use ($user, $groups): int {
            $available = collect($this->duplicates->execute($user))->keyBy('primary.id');
            $used = [];
            $merged = 0;

            foreach ($groups as $group) {
                $primaryId = $group['primary_id'];
                $duplicateIds = $group['duplicate_ids'];
                $suggestion = $available->get($primaryId);
                $expectedIds = collect($suggestion['duplicates'] ?? [])->pluck('id')->sort()->values()->all();
                $submittedIds = collect($duplicateIds)->sort()->values()->all();

                if ($suggestion === null || $expectedIds !== $submittedIds || isset($used[$primaryId])) {
                    throw ValidationException::withMessages(['groups' => 'La propuesta de fusión ha cambiado. Revisa los duplicados de nuevo.']);
                }

                $duplicateIds = collect($suggestion['duplicates'])->pluck('id')->all();
                $used[$primaryId] = true;
                foreach ($duplicateIds as $duplicateId) {
                    if (isset($used[$duplicateId])) {
                        throw ValidationException::withMessages(['groups' => 'Un contacto no puede fusionarse más de una vez.']);
                    }
                    $used[$duplicateId] = true;
                }

                $contacts = Contact::query()->visibleTo($user)
                    ->whereIn('id', [$primaryId, ...$duplicateIds])
                    ->lockForUpdate()->get()->keyBy('id');
                if ($contacts->count() !== count($duplicateIds) + 1) {
                    throw ValidationException::withMessages(['groups' => 'Alguno de los contactos ya no está disponible.']);
                }

                $primary = $contacts->get($primaryId);
                $sourceRecordIds = $this->externalRecordIds($primaryId);
                foreach ($duplicateIds as $duplicateId) {
                    array_push($sourceRecordIds, ...$this->externalRecordIds($duplicateId));
                    $duplicate = $contacts->get($duplicateId);
                    if ($primary->user_id !== $duplicate->user_id || $primary->household_id !== $duplicate->household_id) {
                        throw ValidationException::withMessages(['groups' => 'Solo se pueden fusionar contactos de la misma libreta.']);
                    }

                    $this->moveReferences($duplicateId, $primaryId);
                    ContactRecord::query()->where('contact_id', $duplicateId)->update(['contact_id' => $primaryId]);
                    $duplicate->delete();
                    $merged++;
                }

                foreach ($sourceRecordIds as $order => $recordId) {
                    ContactRecord::query()->whereKey($recordId)->update(['source_order' => $order]);
                }

                $this->selectPreferredRecords($primary);
            }

            return $merged;
        });
    }

    /** @return list<string> */
    private function externalRecordIds(string $contactId): array
    {
        return ContactRecord::query()->where('contact_id', $contactId)
            ->whereNotNull('contact_source_id')
            ->orderBy('source_order')->orderBy('created_at')->orderBy('id')
            ->pluck('id')->all();
    }

    private function moveReferences(string $fromId, string $toId): void
    {
        foreach (DB::table('economic_transaction_contacts')->where('contact_id', $fromId)->get() as $row) {
            DB::table('economic_transaction_contacts')->insertOrIgnore([
                'economic_transaction_id' => $row->economic_transaction_id,
                'contact_id' => $toId,
                'role' => $row->role,
            ]);
        }
        DB::table('economic_transaction_contacts')->where('contact_id', $fromId)->delete();

        foreach (DB::table('contact_contact_label')->where('contact_id', $fromId)->get() as $row) {
            $existing = DB::table('contact_contact_label')
                ->where('contact_id', $toId)->where('contact_label_id', $row->contact_label_id)->first();
            if ($existing === null) {
                DB::table('contact_contact_label')->insert([
                    'contact_id' => $toId,
                    'contact_label_id' => $row->contact_label_id,
                    'manual' => $row->manual,
                    'imported' => $row->imported,
                ]);
            } else {
                DB::table('contact_contact_label')
                    ->where('contact_id', $toId)->where('contact_label_id', $row->contact_label_id)
                    ->update([
                        'manual' => $existing->manual || $row->manual,
                        'imported' => $existing->imported || $row->imported,
                    ]);
            }
        }
        DB::table('contact_contact_label')->where('contact_id', $fromId)->delete();

        foreach (['household_members', 'household_invitations', 'contact_record_relations'] as $table) {
            $column = $table === 'contact_record_relations' ? 'related_contact_id' : 'contact_id';
            DB::table($table)->where($column, $fromId)->update([$column => $toId]);
        }
    }

    private function selectPreferredRecords(Contact $contact): void
    {
        $records = ContactRecord::query()->where('contact_id', $contact->id)
            ->whereNull('remote_deleted_at')
            ->orderByRaw('CASE WHEN contact_source_id IS NULL THEN 0 ELSE 1 END')
            ->orderBy('source_order')->orderBy('created_at')->orderBy('id')->get();
        $preferred = $records->first();

        $photoRecord = DB::table('contact_record_photos')
            ->join('contact_records', 'contact_records.id', '=', 'contact_record_photos.contact_record_id')
            ->where('contact_records.contact_id', $contact->id)
            ->whereNull('contact_records.remote_deleted_at')
            ->orderByRaw('CASE WHEN contact_records.contact_source_id IS NULL THEN 0 ELSE 1 END')
            ->orderBy('contact_records.source_order')
            ->orderBy('contact_records.created_at')->orderBy('contact_records.id')
            ->value('contact_records.id');

        $contact->update([
            'preferred_record_id' => $preferred?->id,
            'preferred_photo_record_id' => $photoRecord,
            'display_name' => $preferred?->formatted_name ?: $contact->display_name,
        ]);
    }
}
