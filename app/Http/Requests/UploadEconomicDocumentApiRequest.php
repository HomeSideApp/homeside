<?php

namespace App\Http\Requests;

class UploadEconomicDocumentApiRequest extends UploadEconomicDocumentRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['file' => ['required', 'file', 'max:10240', 'mimetypes:image/jpeg,image/png,image/webp,image/heic,application/pdf']];
    }
}
