<?php

namespace App\Actions\Contacts;

use App\Models\Contact;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class FindContactDuplicates
{
    /**
     * @return list<array{primary: array{id: string, name: string}, duplicates: list<array{id: string, name: string}>, matches: list<string>}>
     */
    public function execute(User $user): array
    {
        $contacts = Contact::query()->visibleTo($user)
            ->select(['id', 'user_id', 'household_id', 'display_name', 'created_at'])
            ->orderBy('created_at')->orderBy('id')->get();
        if ($contacts->count() < 2) {
            return [];
        }

        $byId = $contacts->keyBy('id');
        $adjacent = [];
        $matches = [];
        $identities = [];

        foreach (['emails', 'phones'] as $kind) {
            $values = DB::table('contact_'.$kind)
                ->join('contact_records', 'contact_records.id', '=', 'contact_'.$kind.'.contact_record_id')
                ->whereIn('contact_records.contact_id', $contacts->modelKeys())
                ->whereNull('contact_records.remote_deleted_at')
                ->get(['contact_records.contact_id', 'contact_'.$kind.'.value']);

            foreach ($values as $value) {
                $contact = $byId->get($value->contact_id);
                if ($contact === null) {
                    continue;
                }

                $normalized = $kind === 'emails'
                    ? mb_strtolower(trim($value->value))
                    : preg_replace('/\D+/', '', $value->value);
                if ($normalized === '' || ($kind === 'phones' && strlen($normalized) < 7)) {
                    continue;
                }

                $scope = $contact->user_id ?? $contact->household_id;
                $key = $scope.'|'.$kind.'|'.$normalized;
                $identities[$key][$contact->id] = true;
            }
        }

        foreach ($identities as $key => $ids) {
            $ids = array_keys($ids);
            if (count($ids) < 2) {
                continue;
            }

            foreach ($ids as $id) {
                foreach ($ids as $otherId) {
                    if ($id !== $otherId) {
                        $adjacent[$id][$otherId] = true;
                        $matches[$id][$key] = true;
                    }
                }
            }
        }

        $groups = [];
        $visited = [];
        foreach ($contacts as $contact) {
            if (isset($visited[$contact->id]) || ! isset($adjacent[$contact->id])) {
                continue;
            }

            $pending = [$contact->id];
            $ids = [];
            $shared = [];
            while ($pending !== []) {
                $id = array_pop($pending);
                if (isset($visited[$id])) {
                    continue;
                }

                $visited[$id] = true;
                $ids[] = $id;
                foreach (array_keys($matches[$id] ?? []) as $key) {
                    $parts = explode('|', $key, 3);
                    $shared[] = $parts[1] === 'emails' ? 'Correo: '.$parts[2] : 'Teléfono: '.$parts[2];
                }
                foreach (array_keys($adjacent[$id] ?? []) as $otherId) {
                    if (! isset($visited[$otherId])) {
                        $pending[] = $otherId;
                    }
                }
            }

            $members = $contacts->filter(fn (Contact $item): bool => in_array($item->id, $ids, true))->values();
            $groups[] = [
                'primary' => ['id' => $members[0]->id, 'name' => $members[0]->display_name],
                'duplicates' => $members->skip(1)->map(fn (Contact $item): array => ['id' => $item->id, 'name' => $item->display_name])->values()->all(),
                'matches' => array_values(array_unique($shared)),
            ];
        }

        return $groups;
    }
}
