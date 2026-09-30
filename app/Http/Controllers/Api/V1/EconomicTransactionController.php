<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Economy\CreateEconomicTransaction;
use App\Actions\Economy\DeleteEconomicTransaction;
use App\Actions\Economy\GetEconomicTransaction;
use App\Actions\Economy\GetEconomyStats;
use App\Actions\Economy\GetPersonalTotalsStats;
use App\Actions\Economy\ListEconomicTransactions;
use App\Actions\Economy\UpdateEconomicTransaction;
use App\Data\Economy\CreateEconomicTransactionData;
use App\Data\Economy\UpdateEconomicTransactionData;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreEconomicTransactionApiRequest;
use App\Http\Requests\UpdateEconomicTransactionApiRequest;
use App\Http\Resources\Economy\EconomicTransactionResource;
use App\Models\EconomicTransaction;
use App\Models\Household;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

/**
 * Class EconomicTransactionController
 *
 * This controller handles the API endpoints for managing economic transactions,
 * including listing household and personal transactions, computing personal totals
 * and creating, viewing, updating and deleting transactions.
 */
final class EconomicTransactionController extends Controller
{
    /**
     * List the transactions of a household.
     *
     * @param  Request  $request  The incoming HTTP request, optionally containing filter parameters.
     * @param  ListEconomicTransactions  $action  The action that lists the household transactions.
     * @return AnonymousResourceCollection The collection of EconomicTransactionResource instances.
     */
    public function index(Request $request, ListEconomicTransactions $action): AnonymousResourceCollection
    {
        $household = $this->resolveHousehold($request);
        abort_unless($household instanceof Household, 404);
        $this->authorize('view', $household);

        $filters = $request->validate([
            'search' => ['sometimes', 'string', 'max:100'],
            'type' => ['sometimes', 'in:expense,income'],
            'scope' => ['sometimes', 'in:personal,shared'],
            'created_by' => ['sometimes', 'uuid'],
            'from' => ['sometimes', 'date_format:Y-m-d'],
            'to' => ['sometimes', 'date_format:Y-m-d', 'after_or_equal:from'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'perPage' => ['sometimes', 'integer', 'between:1,100'],
            'sort' => ['sometimes', 'in:occurred_at,amount_minor,created_at'],
            'direction' => ['sometimes', 'in:asc,desc'],
        ]);

        if (isset($filters['created_by'])) {
            abort_unless($household->members()->where('user_id', $filters['created_by'])->exists(), 404);
        }

        $transactions = $action->execute($household, $this->authenticatedUser($request), $filters);

        return EconomicTransactionResource::collection($transactions);
    }

    /**
     * Return household economy totals equivalent to the web overview statistics.
     */
    public function totals(Request $request, GetEconomyStats $action): JsonResponse
    {
        $household = $this->resolveHousehold($request);
        abort_unless($household instanceof Household, 404);
        $this->authorize('view', $household);

        $validated = $request->validate(['period' => ['sometimes', 'date_format:Y-m']]);

        return response()->json(['data' => $action->execute(
            $household,
            $this->authenticatedUser($request),
            $validated['period'] ?? null,
        )]);
    }

    /**
     * List the user's personal transactions.
     *
     * @param  Request  $request  The incoming HTTP request, optionally containing filter parameters.
     * @param  ListEconomicTransactions  $action  The action that lists the user's personal transactions.
     * @return AnonymousResourceCollection The collection of the user's personal EconomicTransactionResource instances.
     */
    public function personalIndex(Request $request, ListEconomicTransactions $action): AnonymousResourceCollection
    {
        $filters = $request->validate([
            'search' => ['sometimes', 'string', 'max:100'],
            'type' => ['sometimes', 'in:expense,income'],
            'from' => ['sometimes', 'date_format:Y-m-d'],
            'to' => ['sometimes', 'date_format:Y-m-d', 'after_or_equal:from'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'perPage' => ['sometimes', 'integer', 'between:1,100'],
            'sort' => ['sometimes', 'in:occurred_at,amount_minor,created_at'],
            'direction' => ['sometimes', 'in:asc,desc'],
        ]);

        return EconomicTransactionResource::collection(
            $action->executePersonal($this->authenticatedUser($request), $filters)
        );
    }

    /**
     * Get the user's personal totals.
     *
     * @param  Request  $request  The incoming HTTP request, optionally containing a period filter.
     * @param  GetPersonalTotalsStats  $action  The action that computes the personal totals.
     * @return JsonResponse The JSON response with the aggregated personal totals structure.
     */
    public function personalTotals(Request $request, GetPersonalTotalsStats $action): JsonResponse
    {
        $validated = $request->validate(['period' => ['sometimes', 'date_format:Y-m']]);

        return response()->json(['data' => $action->execute($this->authenticatedUser($request), $validated['period'] ?? null)]);
    }

    /**
     * Create a new economic transaction.
     *
     * @param  StoreEconomicTransactionApiRequest  $request  The validated request with the transaction data.
     * @param  CreateEconomicTransaction  $action  The action that creates the transaction.
     * @return JsonResponse The JSON response with the created EconomicTransactionResource.
     */
    public function store(StoreEconomicTransactionApiRequest $request, CreateEconomicTransaction $action): JsonResponse
    {
        $household = $this->resolveHousehold($request);
        $data = CreateEconomicTransactionData::fromArray($request->validated());
        $transaction = $action->execute($data, $household, $this->authenticatedUser($request));

        return EconomicTransactionResource::make($transaction)
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Show an economic transaction.
     *
     * @param  Request  $request  The incoming HTTP request containing the transaction route parameter.
     * @param  GetEconomicTransaction  $action  The action that retrieves the transaction.
     * @return EconomicTransactionResource The resource representation of the transaction.
     */
    public function show(Request $request, GetEconomicTransaction $action): EconomicTransactionResource
    {
        $transaction = EconomicTransaction::query()->whereKey($request->route('transaction'))->firstOrFail();
        $this->ensureRouteScope($request, $transaction->household_id);
        $this->authorize('view', $transaction);

        return new EconomicTransactionResource($action->execute($transaction));
    }

    /**
     * Update an economic transaction.
     *
     * @param  UpdateEconomicTransactionApiRequest  $request  The validated request with the transaction data.
     * @param  UpdateEconomicTransaction  $action  The action that updates the transaction.
     * @return EconomicTransactionResource The updated resource representation of the transaction.
     */
    public function update(UpdateEconomicTransactionApiRequest $request, UpdateEconomicTransaction $action): EconomicTransactionResource
    {
        $transaction = EconomicTransaction::query()->whereKey($request->route('transaction'))->firstOrFail();
        $data = UpdateEconomicTransactionData::fromArray($request->validated());

        return new EconomicTransactionResource($action->execute($transaction, $data));
    }

    /**
     * Delete an economic transaction.
     *
     * @param  Request  $request  The incoming HTTP request containing the transaction route parameter.
     * @param  DeleteEconomicTransaction  $action  The action that deletes the transaction.
     * @return Response An empty 204 no-content response.
     */
    public function destroy(Request $request, DeleteEconomicTransaction $action): Response
    {
        $transaction = EconomicTransaction::query()->whereKey($request->route('transaction'))->firstOrFail();
        $this->ensureRouteScope($request, $transaction->household_id);
        $this->authorize('delete', $transaction);
        $action->execute($transaction);

        return response()->noContent();
    }

    /**
     * Resolve the household from the route if present (null in economy/me/* routes).
     *
     * Project convention: the FormRequest validates before the model binding,
     * so the household is resolved manually.
     *
     * @param  Request  $request  The incoming HTTP request.
     * @return Household|null The resolved household model instance, or null for personal routes.
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
     * Ensure that a transaction belongs to the household represented by the current route.
     *
     * @param  Request  $request  The incoming request containing the optional household route parameter.
     * @param  string|null  $resourceHouseholdId  The household identifier stored on the transaction.
     * @return void This guard does not return a value.
     */
    private function ensureRouteScope(Request $request, ?string $resourceHouseholdId): void
    {
        abort_unless($resourceHouseholdId === $this->resolveHousehold($request)?->id, 403);
    }
}
