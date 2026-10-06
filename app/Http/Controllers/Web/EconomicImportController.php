<?php

namespace App\Http\Controllers\Web;

use App\Actions\Economy\ConfirmEconomicImport;
use App\Actions\Economy\CreateEconomicImport;
use App\Actions\Economy\DiscardEconomicImport;
use App\Actions\Economy\ListEconomicImports;
use App\Actions\Economy\ReprocessEconomicImport;
use App\Actions\Economy\RetryEconomicImport;
use App\Actions\Economy\UploadEconomicDocument;
use App\Data\Economy\ConfirmEconomicImportData;
use App\Data\Economy\CreateEconomicImportData;
use App\Data\Economy\ImageProcessingData;
use App\Data\Economy\ImportFiltersData;
use App\Http\Controllers\Controller;
use App\Http\Requests\ConfirmEconomicImportRequest;
use App\Http\Requests\CreateEconomicTransactionFormRequest;
use App\Http\Requests\DiscardEconomicImportRequest;
use App\Http\Requests\ReadEconomyRequest;
use App\Http\Requests\ReprocessEconomicImportRequest;
use App\Http\Requests\RetryEconomicImportRequest;
use App\Http\Requests\StoreEconomicImportUploadRequest;
use App\Http\Requests\ViewEconomicImportRequest;
use App\Http\Resources\Economy\EconomicImportResource;
use App\Models\EconomicImport;
use App\Models\Household;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Handles the economic import review flow.
 */
final class EconomicImportController extends Controller
{
    /**
     * List the economic imports visible in the current scope.
     *
     * This index is the recovery point for analyses: leaving the review page never loses an
     * import, because its lifecycle state lives in the database and this list links back to it.
     *
     * @param  ListEconomicImports  $action  The action that lists the visible imports.
     * @return Response The Inertia import list response.
     */
    public function index(ReadEconomyRequest $request, ListEconomicImports $action): Response
    {
        $household = $this->resolveHousehold($request);
        $user = $this->authenticatedUser($request);
        $filters = ImportFiltersData::fromArray($request->only([
            'status', 'created_by', 'search', 'perPage',
        ]));

        if ($household !== null) {
            $imports = $action->executeHousehold($household, $user, $filters);
        } else {
            $imports = $action->executePersonal($user, $filters);
        }

        return Inertia::render('economy/imports/Index', [
            'household' => $household,
            'imports' => EconomicImportResource::collection($imports),
            'filters' => [
                ...$request->only(['status', 'created_by', 'search', 'perPage']),
                'status' => $filters->status,
            ],
            'pendingCount' => $action->countPending($user, $household),
            'members' => $household?->members->load('user') ?? [],
        ]);
    }

    /**
     * Show the import creation page.
     *
     * @return Response The HTTP response.
     */
    public function create(CreateEconomicTransactionFormRequest $request): Response
    {
        $household = $this->resolveHousehold($request);

        return Inertia::render('economy/imports/Create', [
            'household' => $household,
        ]);
    }

    /**
     * Upload a document and create its economic import from the Inertia form.
     *
     * @param  StoreEconomicImportUploadRequest  $request  The validated request containing the document and requested extraction sections.
     * @param  UploadEconomicDocument  $uploadDocument  The action that validates, stores, and registers the uploaded document.
     * @param  CreateEconomicImport  $createImport  The action that creates and queues the document import.
     * @return RedirectResponse The redirect response to the newly created import review page.
     */
    public function store(
        StoreEconomicImportUploadRequest $request,
        UploadEconomicDocument $uploadDocument,
        CreateEconomicImport $createImport,
    ): RedirectResponse {
        $household = $this->resolveHousehold($request);
        $user = $this->authenticatedUser($request);
        $processingInput = $request->validated('image_processing');
        $processing = is_array($processingInput) && $processingInput !== []
            ? ImageProcessingData::fromArray($processingInput)
            : null;
        $document = $uploadDocument->execute($request->file('file'), $household, $user, $processing);
        $data = CreateEconomicImportData::fromArray([
            'document_id' => $document->id,
            'sections' => $request->validated('sections'),
        ]);
        $import = $createImport->execute($data, $household, $user);

        return $household !== null
            ? to_route('households.economy.imports.show', [$household, $import])
            : to_route('economy.me.imports.show', $import);
    }

    /**
     * Show the import review page.
     *
     * @param  Household|null  $household  The routed household, when the route is household-scoped.
     * @param  EconomicImport  $import  The economic import model instance.
     * @return Response The HTTP response.
     */
    public function show(ViewEconomicImportRequest $request, ?Household $household, EconomicImport $import): Response
    {

        return Inertia::render('economy/imports/Review', [
            'household' => $this->resolveHousehold($request),
            'importData' => (new EconomicImportResource($import->load(['document', 'aiRun'])))->resolve($request),
            'members' => $import->household?->members->load('user') ?? [],
        ]);
    }

    /**
     * Confirm an import and create the transaction.
     *
     * @param  ConfirmEconomicImportRequest  $request  The incoming HTTP request.
     * @param  Household|null  $household  The routed household, when the route is household-scoped.
     * @param  EconomicImport  $import  The economic import model instance.
     * @param  ConfirmEconomicImport  $action  The action responsible for the operation.
     * @return RedirectResponse The HTTP response.
     */
    public function confirm(ConfirmEconomicImportRequest $request, ?Household $household, EconomicImport $import, ConfirmEconomicImport $action): RedirectResponse
    {
        $data = ConfirmEconomicImportData::fromArray($request->validated());
        $transaction = $action->execute($import, $data, $this->authenticatedUser($request));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('app.toast.transaction_from_analysis')]);

        return $import->household_id !== null
            ? to_route('households.economy.show', [$import->household, $transaction])
            : to_route('economy.me.show', $transaction);
    }

    /**
     * Retry a failed import analysis.
     *
     * @param  Household|null  $household  The routed household, when the route is household-scoped.
     * @param  EconomicImport  $import  The economic import model instance.
     * @param  RetryEconomicImport  $action  The action responsible for the operation.
     * @return RedirectResponse The HTTP response.
     */
    public function retry(RetryEconomicImportRequest $request, ?Household $household, EconomicImport $import, RetryEconomicImport $action): RedirectResponse
    {
        $action->execute($import);

        Inertia::flash('toast', ['type' => 'info', 'message' => __('app.toast.analysis_restarted')]);

        return back();
    }

    /**
     * Regenerate the analysed image with new adjustments and restart the analysis.
     *
     * @param  ReprocessEconomicImportRequest  $request  The validated request carrying the image adjustments.
     * @param  Household|null  $household  The routed household, when the route is household-scoped.
     * @param  EconomicImport  $import  The economic import model instance.
     * @param  ReprocessEconomicImport  $action  The action responsible for the operation.
     * @return RedirectResponse The redirect response back to the review page.
     */
    public function reprocess(
        ReprocessEconomicImportRequest $request,
        ?Household $household,
        EconomicImport $import,
        ReprocessEconomicImport $action,
    ): RedirectResponse {
        $action->execute($import, ImageProcessingData::fromArray($request->validated('image_processing')));

        Inertia::flash('toast', ['type' => 'info', 'message' => __('app.toast.analysis_restarted')]);

        return back();
    }

    /**
     * Discard an economic import.
     *
     * @param  Household|null  $household  The routed household, when the route is household-scoped.
     * @param  EconomicImport  $import  The economic import model instance.
     * @param  DiscardEconomicImport  $action  The action responsible for the operation.
     * @return RedirectResponse The HTTP response.
     */
    public function discard(DiscardEconomicImportRequest $request, ?Household $household, EconomicImport $import, DiscardEconomicImport $action): RedirectResponse
    {
        $action->execute($import);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('app.toast.import_discarded')]);

        return $this->resolveHousehold($request) !== null
            ? to_route('households.economy.index', $this->resolveHousehold($request))
            : to_route('economy.me.index');
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

        return is_string($raw)
            ? Household::query()->whereKey($raw)->firstOrFail()
            : null;
    }
}
