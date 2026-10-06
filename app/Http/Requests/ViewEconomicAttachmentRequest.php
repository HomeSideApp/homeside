<?php

namespace App\Http\Requests;

use App\Models\EconomicTransactionAttachment;

class ViewEconomicAttachmentRequest extends ViewEconomicTransactionRequest
{
    public function authorize(): bool
    {
        if (! parent::authorize()) {
            return false;
        }

        /** @var EconomicTransactionAttachment $attachment */
        $attachment = $this->route('attachment');
        abort_unless($attachment->transaction_id === $this->economicTransaction()->id, 404);

        return true;
    }
}
