<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\PrefillCatalogModelRequest;
use App\Http\Requests\SearchCatalogModelsRequest;
use App\Http\Requests\SearchCatalogProvidersRequest;
use HomeSide\AiAgents\ModelsDev\CatalogPrefill;
use HomeSide\AiAgents\ModelsDev\CatalogQuery;
use Illuminate\Http\JsonResponse;

/**
 * API endpoints for the models.dev catalog.
 *
 * Provides provider/model search and prefill data for the mobile app.
 */
final class CatalogController extends Controller
{
    public function __construct(
        private readonly CatalogQuery $catalog,
    ) {}

    /**
     * Search providers in the models.dev catalog.
     */
    public function searchProviders(SearchCatalogProvidersRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $term = $validated['q'] ?? null;
        $page = $validated['page'] ?? 1;

        $results = $this->catalog->providers($term, perPage: 20)
            ->appends(['q' => $term]);

        return response()->json([
            'data' => $results->items(),
            'meta' => [
                'current_page' => $results->currentPage(),
                'last_page' => $results->lastPage(),
                'per_page' => $results->perPage(),
                'total' => $results->total(),
            ],
        ]);
    }

    /**
     * Search models for a specific provider in the models.dev catalog.
     */
    public function searchModels(SearchCatalogModelsRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $results = $this->catalog->models(
            providerSlug: $validated['provider_slug'],
            search: $validated['q'] ?? null,
            perPage: 20,
        );

        return response()->json([
            'data' => $results->items(),
            'meta' => [
                'current_page' => $results->currentPage(),
                'last_page' => $results->lastPage(),
                'per_page' => $results->perPage(),
                'total' => $results->total(),
            ],
        ]);
    }

    /**
     * Get prefill data for a selected catalog model.
     */
    public function prefill(PrefillCatalogModelRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $prefill = app(CatalogPrefill::class)
            ->prefill($validated['provider_slug'], $validated['model_id']);

        return response()->json([
            'prefill' => $prefill,
        ]);
    }
}
