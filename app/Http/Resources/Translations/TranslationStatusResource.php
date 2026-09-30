<?php

namespace App\Http\Resources\Translations;

use App\Models\TranslationStatus;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin TranslationStatus */
final class TranslationStatusResource extends JsonResource
{
    /**
     * @param  mixed  $request  The incoming HTTP request.
     * @return array<string, mixed>
     */
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'translatable_type' => $this->translatable_type,
            'translatable_id' => $this->translatable_id,
            'locale' => $this->locale,
            'status' => $this->status,
            'published_at' => $this->published_at?->toISOString(),
        ];
    }
}
