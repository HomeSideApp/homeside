<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Economy\ConfirmEconomicImport;
use App\Actions\Economy\CreateEconomicImport;
use App\Actions\Economy\DiscardEconomicImport;
use App\Actions\Economy\RetryEconomicImport;
use App\Data\Economy\ConfirmEconomicImportData;
use App\Data\Economy\CreateEconomicImportData;
use App\Http\Controllers\Controller;
use App\Http\Requests\ConfirmEconomicImportApiRequest;
use App\Http\Requests\StoreEconomicImportRequest;
use App\Http\Resources\Economy\EconomicImportResource;
use App\Http\Resources\Economy\EconomicTransactionResource;
use App\Models\EconomicImport;
use App\Models\Household;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Handles the API economic import lifecycle.
 */
final class EconomicImportController extends Controller
{
    /**
     * Create an economic import.
     *
     * @param  StoreEconomicImportRequest  $request  The incoming HTTP request.
     * @param  CreateEconomicImport  $action  The action responsible for the operation.
     * @return JsonResponse The JSON response.
     */
    public function store(StoreEconomicImportRequest $request, CreateEconomicImport $action): JsonResponse
    {
        $household = $this->resolveHousehold($request);
        $data = CreateEconomicImportData::fromArray($request->validated());
        $import = $action->execute($data, $household, $this->authenticatedUser($request));

        return EconomicImportResource::make($import)
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Show an economic import.
     *
     * @param  Request  $request  The incoming HTTP request.
     * @return EconomicImportResource The EconomicImport resource.
     */
    public function show(Request $request): EconomicImportResource
    {
        $import = EconomicImport::query()->whereKey($request->route('import'))->firstOrFail();
        $this->ensureRouteScope($request, $import->household_id);
        $this->authorize('view', $import);

        return new EconomicImportResource($import->load(['document', 'aiRun']));
    }

    /**
     * Confirm an import and create the transaction.
     *
     * @param  ConfirmEconomicImportApiRequest  $request  The incoming HTTP request.
     * @param  ConfirmEconomicImport  $action  The action responsible for the operation.
     * @return EconomicTransactionResource The EconomicTransaction resource.
     */
    public function confirm(ConfirmEconomicImportApiRequest $request, ConfirmEconomicImport $action): EconomicTransactionResource
    {
        $import = EconomicImport::query()->whereKey($request->route('import'))->firstOrFail();
        $data = ConfirmEconomicImportData::fromArray($request->validated());
        $transaction = $action->execute($import, $data, $this->authenticatedUser($request));

        return new EconomicTransactionResource($transaction);
    }

    /**
     * Retry a failed import analysis.
     *
     * @param  Request  $request  The incoming HTTP request.
     * @param  RetryEconomicImport  $action  The action responsible for the operation.
     * @return JsonResponse The JSON response.
     */
    public function retry(Request $request, RetryEconomicImport $action): JsonResponse
    {
        $import = EconomicImport::query()->whereKey($request->route('import'))->firstOrFail();
        $this->ensureRouteScope($request, $import->household_id);
        $this->authorize('retry', $import);
        $import = $action->execute($import);

        return EconomicImportResource::make($import)->response();
    }

    /**
     * Discard an economic import.
     *
     * @param  Request  $request  The incoming HTTP request.
     * @param  DiscardEconomicImport  $action  The action responsible for the operation.
     * @return Response The HTTP response.
     */
    public function discard(Request $request, DiscardEconomicImport $action): Response
    {
        $import = EconomicImport::query()->whereKey($request->route('import'))->firstOrFail();
        $this->ensureRouteScope($request, $import->household_id);
        $this->authorize('discard', $import);
        $action->execute($import);

        return response()->noContent();
    }

    /**
     * Resolve the household from the route if present (null in economy/me/* routes).
     *
     * @param  Request  $request  The incoming request containing the optional household route parameter.
     * @return Household|null The routed household, or null for a private economy route.
     */
    private function resolveHousehold(Request $request): ?Household
    {
        $param = $request->route('household');

        if ($param instanceof Household) {
            return $param;
        }

        return is_string($param) ? Household::query()->whereKey($param)->firstOrFail() : null;
    }

    /**
     * Ensure that an import belongs to the household represented by the current route.
     *
     * @param  Request  $request  The incoming request containing the optional household route parameter.
     * @param  string|null  $resourceHouseholdId  The household identifier stored on the import.
     * @return void This guard does not return a value.
     */
    private function ensureRouteScope(Request $request, ?string $resourceHouseholdId): void
    {
        abort_unless($resourceHouseholdId === $this->resolveHousehold($request)?->id, 403);
    }
}
