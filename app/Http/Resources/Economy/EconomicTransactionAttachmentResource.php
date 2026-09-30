<?php

namespace App\Http\Resources\Economy;

use App\Models\EconomicTransactionAttachment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin EconomicTransactionAttachment */
final class EconomicTransactionAttachmentResource extends JsonResource
{
    /**
     * Transform a transaction attachment into its public representation.
     *
     * @param  Request  $request  The incoming HTTP request.
     * @return array<string, mixed> The serialized attachment, including whether the viewer uploaded it.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'original_filename' => $this->original_filename,
            'mime_type' => $this->mime_type,
            'size' => $this->size,
            'sort_order' => $this->sort_order,
            'is_mine' => $request->user()?->id === $this->uploaded_by,
            'uploaded_by' => $this->whenLoaded('uploader', fn (): ?array => $this->uploader === null ? null : [
                'id' => $this->uploader->id,
                'name' => $this->uploader->name,
            ]),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
