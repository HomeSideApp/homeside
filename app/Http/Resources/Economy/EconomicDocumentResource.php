<?php

namespace App\Http\Resources\Economy;

use App\Models\EconomicDocument;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin EconomicDocument */
final class EconomicDocumentResource extends JsonResource
{
    /**
     * @param  Request  $request  The incoming HTTP request.
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $isApiRequest = $request->routeIs('api.v1.*');
        $fileRoute = match (true) {
            $isApiRequest && $this->household_id !== null => 'api.v1.households.economy.documents.file',
            $isApiRequest => 'api.v1.economy.me.documents.file',
            $this->household_id !== null => 'households.economy.documents.file',
            default => 'economy.me.documents.file',
        };
        $routeParameters = $this->household_id !== null
            ? [$this->household_id, $this->id]
            : [$this->id];

        return [
            'id' => $this->id,
            'original_filename' => $this->original_filename,
            'mime_type' => $this->mime_type,
            'size' => $this->size,
            'sha256' => $this->sha256,
            'file_url' => route($fileRoute, $routeParameters),
            // Lets the review screen resume the crop editor with what was already applied.
            'processing' => $this->processing_meta,
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
