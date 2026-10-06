<?php

namespace App\Http\Controllers\Web;

use App\Actions\Economy\DeleteTransactionAttachment;
use App\Actions\Economy\UploadTransactionAttachment;
use App\Http\Controllers\Controller;
use App\Http\Requests\DeleteEconomicAttachmentRequest;
use App\Http\Requests\UploadEconomicAttachmentRequest;
use App\Http\Requests\ViewEconomicAttachmentRequest;
use App\Models\EconomicTransaction;
use App\Models\EconomicTransactionAttachment;
use App\Models\Household;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Class EconomicTransactionAttachmentController
 *
 * Serves and manages the images attached to an economic transaction. Attachments inherit the
 * visibility of their transaction: shared movements are visible to the whole household, while
 * personal movements stay private to their creator.
 */
final class EconomicTransactionAttachmentController extends Controller
{
    /**
     * Store an uploaded attachment for a transaction.
     *
     * @param  Household|null  $household  The routed household, when the route is household-scoped.
     * @param  EconomicTransaction  $transaction  The transaction receiving the attachment.
     * @param  UploadTransactionAttachment  $action  The action that validates and stores the image.
     * @return RedirectResponse The redirect response back to the transaction form.
     */
    public function store(
        UploadEconomicAttachmentRequest $request,
        ?Household $household,
        EconomicTransaction $transaction,
        UploadTransactionAttachment $action,
    ): RedirectResponse {

        $action->execute($transaction, $request->file('image'), $this->authenticatedUser($request));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('app.toast.attachment_uploaded')]);

        return back();
    }

    /**
     * Serve an attachment image inline.
     *
     * @param  Household|null  $household  The routed household, when the route is household-scoped.
     * @param  EconomicTransaction  $transaction  The transaction the attachment belongs to.
     * @param  EconomicTransactionAttachment  $attachment  The attachment to serve.
     * @return StreamedResponse The streamed image response.
     */
    public function file(
        ViewEconomicAttachmentRequest $request,
        ?Household $household,
        EconomicTransaction $transaction,
        EconomicTransactionAttachment $attachment,
    ): StreamedResponse {

        abort_unless(Storage::disk($attachment->disk)->exists($attachment->path), 404);

        return Storage::disk($attachment->disk)->response(
            $attachment->path,
            $attachment->original_filename,
            ['Content-Type' => $attachment->mime_type],
        );
    }

    /**
     * Delete an attachment from a transaction.
     *
     * Any member who can edit a shared transaction may add attachments to it, but only the user
     * who uploaded a given attachment may remove it.
     *
     * @param  Household|null  $household  The routed household, when the route is household-scoped.
     * @param  EconomicTransaction  $transaction  The transaction the attachment belongs to.
     * @param  EconomicTransactionAttachment  $attachment  The attachment to delete.
     * @param  DeleteTransactionAttachment  $action  The action that removes the record and its file.
     * @return RedirectResponse The redirect response back to the transaction form.
     */
    public function destroy(
        DeleteEconomicAttachmentRequest $request,
        ?Household $household,
        EconomicTransaction $transaction,
        EconomicTransactionAttachment $attachment,
        DeleteTransactionAttachment $action,
    ): RedirectResponse {

        $action->execute($attachment);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('app.toast.attachment_deleted')]);

        return back();
    }
}
