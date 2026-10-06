<?php

namespace App\Http\Controllers\Web;

use App\Actions\Economy\UploadEconomicDocument;
use App\Http\Controllers\Controller;
use App\Http\Requests\DeleteEconomicDocumentRequest;
use App\Http\Requests\UploadEconomicDocumentRequest;
use App\Http\Requests\ViewEconomicDocumentRequest;
use App\Http\Resources\Economy\EconomicDocumentResource;
use App\Models\EconomicDocument;
use App\Models\Household;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Handles economic document uploads and downloads.
 */
final class EconomicDocumentController extends Controller
{
    /**
     * Upload a new economic document.
     *
     * @param  UploadEconomicDocument  $action  The action responsible for the operation.
     * @return JsonResponse The JSON response.
     */
    public function store(UploadEconomicDocumentRequest $request, UploadEconomicDocument $action): JsonResponse
    {
        $household = $this->resolveHousehold($request);

        $document = $action->execute($request->file('file'), $household, $request->user());

        return response()->json([
            'document' => new EconomicDocumentResource($document),
        ], 201);
    }

    /**
     * Delete an economic document.
     *
     * @param  Household|null  $household  The routed household, when the route is household-scoped.
     * @param  EconomicDocument  $document  The economic document model instance.
     * @return JsonResponse The JSON response.
     */
    public function destroy(DeleteEconomicDocumentRequest $request, ?Household $household, EconomicDocument $document): JsonResponse
    {
        $document->delete();

        return response()->noContent();
    }

    /**
     * Download an economic document file.
     *
     * @param  Household|null  $household  The routed household, when the route is household-scoped.
     * @param  EconomicDocument  $document  The economic document model instance.
     * @return StreamedResponse The HTTP response.
     */
    public function file(ViewEconomicDocumentRequest $request, ?Household $household, EconomicDocument $document): StreamedResponse
    {

        return Storage::disk($document->disk)
            ->download($document->path, $document->original_filename);
    }

    /**
     * Resolve the household from the route, if present.
     *
     * @return Household|null The routed household, or null for a private economy route.
     */
    private function resolveHousehold(Request $request): ?Household
    {
        $raw = $request->route('household');

        if ($raw instanceof Household) {
            return $raw;
        }

        return $raw ? Household::findOrFail($raw) : null;
    }

    /**
     * Ensure that a document belongs to the household represented by the current route.
     *
     * @param  string|null  $resourceHouseholdId  The household identifier stored on the document.
     * @return void This guard does not return a value.
     */
}
