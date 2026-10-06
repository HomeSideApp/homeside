<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Economy\UploadEconomicDocument;
use App\Http\Controllers\Controller;
use App\Http\Requests\DeleteEconomicDocumentRequest;
use App\Http\Requests\UploadEconomicDocumentApiRequest;
use App\Http\Requests\ViewEconomicDocumentRequest;
use App\Http\Resources\Economy\EconomicDocumentResource;
use App\Models\Household;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Class EconomicDocumentController
 *
 * This controller handles the API endpoints for managing economic documents, including
 * uploading, viewing, deleting and downloading document files.
 */
final class EconomicDocumentController extends Controller
{
    /**
     * Upload and persist a new economic document in a household or private scope.
     *
     * @param  UploadEconomicDocument  $action  The action used to validate and store the document.
     * @param  Household|null  $household  The routed household, or null for a private document.
     * @return JsonResponse The resource response for the created document.
     */
    public function store(UploadEconomicDocumentApiRequest $request, UploadEconomicDocument $action, ?Household $household = null): JsonResponse
    {
        $document = $action->execute($request->file('file'), $household, $this->authenticatedUser($request));

        return EconomicDocumentResource::make($document)->response()->setStatusCode(201);
    }

    /**
     * Return an authorized economic document from the current route scope.
     *
     * @return EconomicDocumentResource The resource representation of the requested document.
     */
    public function show(ViewEconomicDocumentRequest $request): EconomicDocumentResource
    {
        $document = $request->economicDocument();

        return new EconomicDocumentResource($document);
    }

    /**
     * Delete an authorized economic document from the current route scope.
     *
     * @return Response An empty 204 no-content response.
     */
    public function destroy(DeleteEconomicDocumentRequest $request): Response
    {
        $document = $request->economicDocument();
        $document->delete();

        return response()->noContent();
    }

    /**
     * Download an authorized economic document file from the current route scope.
     *
     * @return StreamedResponse The streamed document download response.
     */
    public function file(ViewEconomicDocumentRequest $request): StreamedResponse
    {
        $document = $request->economicDocument();

        return Storage::disk($document->disk)->download(
            $document->path,
            basename($document->original_filename),
            [
                'Content-Type' => $document->mime_type,
                'Content-Length' => (string) $document->size,
                'ETag' => '"'.$document->sha256.'"',
                'Cache-Control' => 'private, no-cache',
            ],
        );
    }
}
