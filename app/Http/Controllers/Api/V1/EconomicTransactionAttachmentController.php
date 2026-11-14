<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Economy\UploadTransactionAttachment;
use App\Http\Controllers\Controller;
use App\Http\Requests\UploadEconomicAttachmentRequest;
use App\Http\Resources\Economy\EconomicTransactionAttachmentResource;
use App\Models\EconomicTransaction;
use App\Models\Household;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Adjuntos de un movimiento.
 *
 * El web ya podía adjuntar un ticket a un movimiento existente, pero esas rutas
 * sólo existían como pantallas Inertia: la API no tenía forma de hacerlo, así que
 * un cliente móvil no podía adjuntar nada después de crear el gasto.
 */
final class EconomicTransactionAttachmentController extends Controller
{
    public function store(
        UploadEconomicAttachmentRequest $request,
        ?Household $household,
        EconomicTransaction $transaction,
        UploadTransactionAttachment $action,
    ): JsonResponse {
        $attachment = $action->execute($transaction, $request->file('image'), $this->authenticatedUser($request));

        return EconomicTransactionAttachmentResource::make($attachment)
            ->response()
            ->setStatusCode(201);
    }

    public function destroy(
        Request $request,
        ?Household $household,
        EconomicTransaction $transaction,
        string $attachment,
    ): Response {
        $this->authorizeTransaction($request, $household, $transaction);

        $transaction->attachments()->whereKey($attachment)->delete();

        return response()->noContent();
    }

    /**
     * A household route must own the transaction; a private one must be the user's.
     */
    private function authorizeTransaction(Request $request, ?Household $household, EconomicTransaction $transaction): void
    {
        $ownsIt = $household === null
            ? $transaction->household_id === null && $transaction->created_by === $request->user()?->id
            : $transaction->household_id === $household->id;

        abort_unless($ownsIt, 404);
    }
}
