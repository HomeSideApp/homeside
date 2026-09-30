<?php

namespace App\Http\Controllers\Web;

use App\Actions\Economy\UploadEconomicDocument;
use App\Http\Controllers\Controller;
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
     * @param  Request  $request  The incoming HTTP request.
     * @param  UploadEconomicDocument  $action  The action responsible for the operation.
     * @return JsonResponse The JSON response.
     */
    public function store(Request $request, UploadEconomicDocument $action): JsonResponse
    {
        $household = $this->resolveHousehold($request);

        if ($household !== null) {
            $this->authorize('view', $household);
        }

        $request->validate([
            'file' => ['required', 'file', 'max:10240'],
        ]);

        $document = $action->execute($request->file('file'), $household, $request->user());

        return response()->json([
            'document' => new EconomicDocumentResource($document),
        ], 201);
    }

    /**
     * Delete an economic document.
     *
     * @param  Request  $request  The incoming request containing the optional household route parameter.
     * @param  Household|null  $household  The routed household, when the route is household-scoped.
     * @param  EconomicDocument  $document  The economic document model instance.
     * @return JsonResponse The JSON response.
     */
    public function destroy(Request $request, ?Household $household, EconomicDocument $document): JsonResponse
    {
        $this->ensureRouteScope($request, $document->household_id);
        $this->authorize('delete', $document);
        $document->delete();

        return response()->noContent();
    }

    /**
     * Download an economic document file.
     *
     * @param  Request  $request  The incoming request containing the optional household route parameter.
     * @param  Household|null  $household  The routed household, when the route is household-scoped.
     * @param  EconomicDocument  $document  The economic document model instance.
     * @return StreamedResponse The HTTP response.
     */
    public function file(Request $request, ?Household $household, EconomicDocument $document): StreamedResponse
    {
        $this->ensureRouteScope($request, $document->household_id);
        $this->authorize('view', $document);

        return Storage::disk($document->disk)
            ->download($document->path, $document->original_filename);
    }

    /**
     * Resolve the household from the route, if present.
     *
     * @param  Request  $request  The incoming request containing the optional household route parameter.
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
     * @param  Request  $request  The incoming request containing the optional household route parameter.
     * @param  string|null  $resourceHouseholdId  The household identifier stored on the document.
     * @return void This guard does not return a value.
     */
    private function ensureRouteScope(Request $request, ?string $resourceHouseholdId): void
    {
        abort_unless($resourceHouseholdId === $this->resolveHousehold($request)?->id, 403);
    }
}
