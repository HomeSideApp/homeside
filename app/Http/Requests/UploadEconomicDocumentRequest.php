<?php

namespace App\Http\Requests;

class UploadEconomicDocumentRequest extends CreateEconomicTransactionFormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['file' => ['required', 'file', 'max:10240']];
    }
}
