<?php

namespace App\Http\Resources\Contacts;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\DB;

class ContactResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'display_name' => $this->display_name,
            'email' => $this->whenHas('primary_email'),
            'phone' => $this->whenHas('primary_phone'),
            'is_favorite' => $this->whenHas('is_favorite'),
            'labels' => $this->whenLoaded('labels', fn (): array => $this->labels->map(fn ($label): array => ['id' => $label->id, 'name' => $label->name, 'manual' => (bool) $label->pivot->manual, 'imported' => (bool) $label->pivot->imported])->all()),
            'household_id' => $this->household_id,
            'avatar_url' => $this->preferred_photo_record_id ? route('contacts.avatar', $this->id) : null,
            'can_remove_photo' => $this->whenLoaded('records', fn (): bool => $this->records->contains(fn ($record): bool => $record->id === $this->preferred_photo_record_id && $record->contact_source_id === null)),
            'records' => $this->whenLoaded('records', function (): array {
                return $this->records->filter(fn ($record): bool => $record->remote_deleted_at === null)
                    ->sortBy(fn ($record): string => sprintf(
                        '%d-%010d-%s',
                        $record->contact_source_id === null ? 0 : 1,
                        $record->source_order,
                        $record->id,
                    ))->map(function ($record): array {
                        $values = [];
                        foreach (['emails', 'phones', 'addresses', 'urls'] as $kind) {
                            $values[$kind] = DB::table('contact_'.$kind)
                                ->where('contact_record_id', $record->id)
                                ->get(['value', 'type', 'preferred'])
                                ->all();
                        }

                        return [
                            'id' => $record->id,
                            'source_id' => $record->contact_source_id,
                            'has_photo' => DB::table('contact_record_photos')->where('contact_record_id', $record->id)->exists(),
                            'photo_checksum' => DB::table('contact_record_photos')->where('contact_record_id', $record->id)->value('checksum'),
                            'formatted_name' => $record->formatted_name,
                            'given_name' => $record->given_name,
                            'family_name' => $record->family_name,
                            'additional_name' => $record->additional_name,
                            'nickname' => $record->nickname,
                            'organization' => $record->organization,
                            'job_title' => $record->job_title,
                            'birthday' => $record->birthday?->toDateString(),
                            'notes' => $record->notes,
                            'dates' => DB::table('contact_record_dates')->where('contact_record_id', $record->id)->get(['kind', 'label', 'value', 'value_type'])->all(),
                            'relations' => DB::table('contact_record_relations')->where('contact_record_id', $record->id)->get(['type', 'related_contact_id', 'name', 'external_value'])->all(),
                            ...$values,
                        ];
                    })->values()->all();
            }),
        ];
    }
}
