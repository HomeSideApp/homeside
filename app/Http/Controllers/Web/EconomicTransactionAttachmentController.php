<?php

namespace App\Http\Controllers\Web;

use App\Actions\Economy\DeleteTransactionAttachment;
use App\Actions\Economy\UploadTransactionAttachment;
use App\Http\Controllers\Controller;
use App\Models\EconomicTransaction;
use App\Models\EconomicTransactionAttachment;
use App\Models\Household;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
     * @param  Request  $request  The incoming multipart request carrying the image.
     * @param  Household|null  $household  The routed household, when the route is household-scoped.
     * @param  EconomicTransaction  $transaction  The transaction receiving the attachment.
     * @param  UploadTransactionAttachment  $action  The action that validates and stores the image.
     * @return RedirectResponse The redirect response back to the transaction form.
     */
    public function store(
        Request $request,
        ?Household $household,
        EconomicTransaction $transaction,
        UploadTransactionAttachment $action,
    ): RedirectResponse {
        $this->ensureRouteScope($request, $transaction->household_id);
        $this->authorize('update', $transaction);

        $request->validate([
            'image' => ['required', 'image', 'mimes:jpg,jpeg,png,webp,heic', 'max:10240'],
        ]);

        $action->execute($transaction, $request->file('image'), $this->authenticatedUser($request));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('app.toast.attachment_uploaded')]);

        return back();
    }

    /**
     * Serve an attachment image inline.
     *
     * @param  Request  $request  The incoming HTTP request.
     * @param  Household|null  $household  The routed household, when the route is household-scoped.
     * @param  EconomicTransaction  $transaction  The transaction the attachment belongs to.
     * @param  EconomicTransactionAttachment  $attachment  The attachment to serve.
     * @return StreamedResponse The streamed image response.
     */
    public function file(
        Request $request,
        ?Household $household,
        EconomicTransaction $transaction,
        EconomicTransactionAttachment $attachment,
    ): StreamedResponse {
        $this->ensureRouteScope($request, $transaction->household_id);
        $this->authorize('view', $transaction);
        abort_unless($attachment->transaction_id === $transaction->id, 404);

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
     * @param  Request  $request  The incoming HTTP request.
     * @param  Household|null  $household  The routed household, when the route is household-scoped.
     * @param  EconomicTransaction  $transaction  The transaction the attachment belongs to.
     * @param  EconomicTransactionAttachment  $attachment  The attachment to delete.
     * @param  DeleteTransactionAttachment  $action  The action that removes the record and its file.
     * @return RedirectResponse The redirect response back to the transaction form.
     */
    public function destroy(
        Request $request,
        ?Household $household,
        EconomicTransaction $transaction,
        EconomicTransactionAttachment $attachment,
        DeleteTransactionAttachment $action,
    ): RedirectResponse {
        $this->ensureRouteScope($request, $transaction->household_id);
        $this->authorize('update', $transaction);
        abort_unless($attachment->transaction_id === $transaction->id, 404);
        abort_unless($attachment->uploaded_by === $this->authenticatedUser($request)->id, 403);

        $action->execute($attachment);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('app.toast.attachment_deleted')]);

        return back();
    }

    /**
     * Ensure the transaction belongs to the household represented by the current route.
     *
     * @param  Request  $request  The incoming HTTP request.
     * @param  string|null  $resourceHouseholdId  The household identifier stored on the transaction.
     * @return void This guard does not return a value.
     */
    private function ensureRouteScope(Request $request, ?string $resourceHouseholdId): void
    {
        $raw = $request->route('household');
        $routeHouseholdId = $raw instanceof Household ? $raw->id : $raw;

        abort_unless($resourceHouseholdId === ($routeHouseholdId ?? null), 403);
    }
}
