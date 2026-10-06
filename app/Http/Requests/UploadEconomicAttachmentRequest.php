<?php

namespace App\Http\Requests;

class UploadEconomicAttachmentRequest extends ManageEconomicTransactionRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['image' => ['required', 'image', 'mimes:jpg,jpeg,png,webp,heic', 'max:10240']];
    }
}
