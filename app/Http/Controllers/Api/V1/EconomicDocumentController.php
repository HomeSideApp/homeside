<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Economy\UploadEconomicDocument;
use App\Http\Controllers\Controller;
use App\Http\Resources\Economy\EconomicDocumentResource;
use App\Models\EconomicDocument;
use App\Models\Household;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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
     * @param  Request  $request  The incoming request containing the uploaded file.
     * @param  UploadEconomicDocument  $action  The action used to validate and store the document.
     * @param  Household|null  $household  The routed household, or null for a private document.
     * @return JsonResponse The resource response for the created document.
     */
    public function store(Request $request, UploadEconomicDocument $action, ?Household $household = null): JsonResponse
    {
        if ($household !== null) {
            $this->authorize('view', $household);
        }

        $request->validate([
            'file' => ['required', 'file', 'max:10240', 'mimetypes:image/jpeg,image/png,image/webp,image/heic,application/pdf'],
        ]);

        $document = $action->execute($request->file('file'), $household, $this->authenticatedUser($request));

        return EconomicDocumentResource::make($document)->response()->setStatusCode(201);
    }

    /**
     * Return an authorized economic document from the current route scope.
     *
     * @param  Request  $request  The incoming request containing the document and optional household parameters.
     * @return EconomicDocumentResource The resource representation of the requested document.
     */
    public function show(Request $request): EconomicDocumentResource
    {
        $document = EconomicDocument::query()->whereKey($request->route('document'))->firstOrFail();
        $this->ensureRouteScope($request, $document->household_id);
        $this->authorize('view', $document);

        return new EconomicDocumentResource($document);
    }

    /**
     * Delete an authorized economic document from the current route scope.
     *
     * @param  Request  $request  The incoming request containing the document and optional household parameters.
     * @return Response An empty 204 no-content response.
     */
    public function destroy(Request $request): Response
    {
        $document = EconomicDocument::query()->whereKey($request->route('document'))->firstOrFail();
        $this->ensureRouteScope($request, $document->household_id);
        $this->authorize('delete', $document);
        $document->delete();

        return response()->noContent();
    }

    /**
     * Download an authorized economic document file from the current route scope.
     *
     * @param  Request  $request  The incoming request containing the document and optional household parameters.
     * @return StreamedResponse The streamed document download response.
     */
    public function file(Request $request): StreamedResponse
    {
        $document = EconomicDocument::query()->whereKey($request->route('document'))->firstOrFail();
        $this->ensureRouteScope($request, $document->household_id);
        $this->authorize('view', $document);

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

    /**
     * Resolve the optional household route parameter.
     *
     * @param  Request  $request  The incoming request containing the optional household route parameter.
     * @return Household|null The routed household, or null for a private economy route.
     */
    private function resolveHousehold(Request $request): ?Household
    {
        $household = $request->route('household');

        if ($household instanceof Household) {
            return $household;
        }

        return is_string($household) ? Household::query()->whereKey($household)->firstOrFail() : null;
    }

    /**
     * Ensure that a document belongs to the household represented by the current route.
     *
     * @param  Request  $request  The incoming request containing the optional household route parameter.
     * @param  string|null  $resourceHouseholdId  The household identifier stored on the document.
     * @return void This guard does not return a value.
     */
    private function ensureRouteScope(Request $request, ?string $resourceHouseholdId): void
    {
        abort_unless($resourceHouseholdId === $this->resolveHousehold($request)?->id, 403);
    }
}
