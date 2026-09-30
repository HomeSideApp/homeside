<?php

namespace App\Actions\Economy;

use App\Models\EconomicTransactionAttachment;
use Illuminate\Support\Facades\Storage;

/**
 * Deletes a transaction attachment and its stored files.
 */
final class DeleteTransactionAttachment
{
    /**
     * Remove the attachment record and the image it references.
     *
     * @param  EconomicTransactionAttachment  $attachment  The attachment to delete.
     * @return void This action does not return a value.
     */
    public function execute(EconomicTransactionAttachment $attachment): void
    {
        if (Storage::disk($attachment->disk)->exists($attachment->path)) {
            Storage::disk($attachment->disk)->delete($attachment->path);
        }

        $attachment->delete();
    }
}
