<?php

namespace App\Http\Controllers\Web;

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
use App\Http\Requests\StoreEconomicTransactionRequest;
use App\Http\Requests\UpdateEconomicTransactionRequest;
use App\Http\Resources\Economy\EconomicAccountResource;
use App\Http\Resources\Economy\EconomicTransactionResource;
use App\Http\Resources\Households\HouseholdMemberResource;
use App\Models\EconomicAccount;
use App\Models\EconomicTransaction;
use App\Models\Household;
use App\Services\Contacts\ContactResolutionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class EconomicTransactionController extends Controller
{
    /**
     * Display the household economy overview and its filtered transaction list.
     *
     * @param  Request  $request  The incoming HTTP request.
     * @param  ListEconomicTransactions  $action  The action used to query visible transactions.
     * @return Response The Inertia economy overview response.
     */
    public function index(Request $request, ListEconomicTransactions $action): Response
    {
        $household = $this->resolveHousehold($request);

        if ($household !== null) {
            $this->authorize('view', $household);
        }

        $transactions = $action->execute($household, $request->user(), [
            'type' => $request->input('type'),
            'scope' => $request->input('scope'),
            'created_by' => $request->input('created_by'),
            'from' => $request->input('from'),
            'to' => $request->input('to'),
            'search' => $request->input('search'),
            'perPage' => $request->input('perPage', 15),
        ]);

        return Inertia::render('economy/Index', [
            'household' => $household,
            'transactions' => EconomicTransactionResource::collection($transactions),
            'filters' => $request->only(['type', 'scope', 'created_by', 'from', 'to', 'search', 'perPage']),
            'stats' => $household !== null
                ? (new GetEconomyStats)->execute($household, $request->user(), $request->input('period'))
                : null,
            'personalTotals' => (new GetPersonalTotalsStats)->execute($request->user()),
            'members' => HouseholdMemberResource::collection($household?->members->load('user') ?? [])->resolve(),
        ]);
    }

    /**
     * Display the transaction creation form for a household or private scope.
     *
     * @param  Request  $request  The incoming HTTP request containing the optional household route parameter.
     * @return Response The Inertia transaction creation response.
     */
    public function create(Request $request): Response
    {
        $household = $this->resolveHousehold($request);

        if ($household !== null) {
            $this->authorize('view', $household);
        }

        return Inertia::render('economy/Create', [
            'household' => $household,
            'members' => HouseholdMemberResource::collection($household?->members->load('user') ?? [])->resolve(),
            // The destination selector can target any household the user belongs to, so the
            // participant selector needs every household's members in one payload.
            'membersByHousehold' => $this->membersByHousehold($request),
            // The split default is stored per household, so every destination needs its own value.
            'splitTypesByHousehold' => $this->splitTypesByHousehold($request),
            'accounts' => $this->availableAccounts($request),
        ]);
    }

    /**
     * Resolve the configured default expense split type of every household the user belongs to.
     *
     * @param  Request  $request  The incoming HTTP request.
     * @return array<string, string> The SplitType value keyed by household id.
     */
    private function splitTypesByHousehold(Request $request): array
    {
        $user = $this->authenticatedUser($request);

        if (! $user->households_enabled) {
            return [];
        }

        return $user->households()
            ->with('modules')
            ->get()
            ->mapWithKeys(function (Household $household): array {
                $economyModule = $household->modules->first(
                    fn ($module): bool => $module->module === 'economy',
                );
                $configured = ($economyModule?->settings ?? [])['default_split_type'] ?? null;

                return [
                    $household->id => in_array($configured, ['equal', 'fixed', 'percentage'], true)
                        ? $configured
                        : 'equal',
                ];
            })
            ->all();
    }

    /**
     * Resolve the members of every household the authenticated user belongs to, keyed by household.
     *
     * @param  Request  $request  The incoming HTTP request.
     * @return array<string, array<int, array<string, mixed>>> The serialized members per household.
     */
    private function membersByHousehold(Request $request): array
    {
        $user = $this->authenticatedUser($request);

        if (! $user->households_enabled) {
            return [];
        }

        $contacts = app(ContactResolutionService::class);

        return $user->households()
            ->with('members.user')
            ->get()
            ->mapWithKeys(fn (Household $household): array => [
                $household->id => $household->members
                    ->map(fn ($member): array => [
                        'id' => $member->id,
                        'user' => [
                            'id' => $member->user->id,
                            'name' => $member->user->name,
                        ],
                        'contact' => $contacts->summary($contacts->forHouseholdMember($member, $user)),
                    ])
                    ->values()
                    ->all(),
            ])
            ->all();
    }

    /**
     * Persist a new economic transaction and redirect to its detail page.
     *
     * @param  StoreEconomicTransactionRequest  $request  The authorized and validated transaction request.
     * @param  CreateEconomicTransaction  $action  The action used to persist the transaction and its relations.
     * @return RedirectResponse The redirect response to the created transaction.
     */
    public function store(StoreEconomicTransactionRequest $request, CreateEconomicTransaction $action): RedirectResponse
    {
        $household = $this->resolveHousehold($request);
        $data = CreateEconomicTransactionData::fromArray($request->validated());
        $transaction = $action->execute($data, $household, $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('app.toast.transaction_created')]);

        return $this->redirectToTransaction($household, $transaction);
    }

    /**
     * Display an economic transaction and its related details.
     *
     * @param  Request  $request  The incoming HTTP request containing the optional household route parameter.
     * @param  Household|null  $household  The routed household, when the route is household-scoped.
     * @param  EconomicTransaction  $transaction  The transaction to display.
     * @param  GetEconomicTransaction  $action  The action used to load the transaction details.
     * @return Response The Inertia transaction detail response.
     */
    public function show(Request $request, ?Household $household, EconomicTransaction $transaction, GetEconomicTransaction $action): Response
    {
        $household = $household?->exists ? $household : null;
        $this->ensureRouteScope($request, $transaction->household_id);
        $this->authorize('view', $transaction);

        return Inertia::render('economy/Show', [
            'household' => $household,
            'transaction' => (new EconomicTransactionResource($action->execute($transaction)))->resolve($request),
        ]);
    }

    /**
     * Display the edit form for an existing economic transaction.
     *
     * @param  Request  $request  The incoming HTTP request containing the optional household route parameter.
     * @param  Household|null  $household  The routed household, when the route is household-scoped.
     * @param  EconomicTransaction  $transaction  The transaction to edit.
     * @return Response The Inertia transaction edit response.
     */
    public function edit(Request $request, ?Household $household, EconomicTransaction $transaction): Response
    {
        $household = $household?->exists ? $household : null;
        $this->ensureRouteScope($request, $transaction->household_id);
        $this->authorize('update', $transaction);

        return Inertia::render('economy/Edit', [
            'household' => $household,
            'transaction' => (new EconomicTransactionResource(
                $transaction->load(['creator', 'items', 'taxes', 'participants.householdMember.user', 'sourceDocument', 'account.paymentMethods', 'attachments.uploader'])
            ))->resolve($request),
            'members' => HouseholdMemberResource::collection($household?->members->load('user') ?? [])->resolve(),
            'splitTypesByHousehold' => $this->splitTypesByHousehold($request),
            'accounts' => $this->availableAccounts($request),
        ]);
    }

    /**
     * Apply validated changes to an economic transaction and redirect to its detail page.
     *
     * @param  UpdateEconomicTransactionRequest  $request  The authorized and validated update request.
     * @param  Household|null  $household  The routed household, when the route is household-scoped.
     * @param  EconomicTransaction  $transaction  The transaction to update.
     * @param  UpdateEconomicTransaction  $action  The action used to persist the update and child collections.
     * @return RedirectResponse The redirect response to the updated transaction.
     */
    public function update(UpdateEconomicTransactionRequest $request, ?Household $household, EconomicTransaction $transaction, UpdateEconomicTransaction $action): RedirectResponse
    {
        $household = $household?->exists ? $household : null;
        $data = UpdateEconomicTransactionData::fromArray($request->validated());
        $action->execute($transaction, $data);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('app.toast.transaction_updated')]);

        return $this->redirectToTransaction($household, $transaction);
    }

    /**
     * Delete an economic transaction and redirect to its owning economy overview.
     *
     * @param  Request  $request  The incoming HTTP request containing the optional household route parameter.
     * @param  Household|null  $household  The routed household, when the route is household-scoped.
     * @param  EconomicTransaction  $transaction  The transaction to delete.
     * @param  DeleteEconomicTransaction  $action  The action used to delete the transaction.
     * @return RedirectResponse The redirect response to the applicable economy overview.
     */
    public function destroy(Request $request, ?Household $household, EconomicTransaction $transaction, DeleteEconomicTransaction $action): RedirectResponse
    {
        $household = $household?->exists ? $household : null;
        $this->ensureRouteScope($request, $transaction->household_id);
        $this->authorize('delete', $transaction);
        $action->execute($transaction);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('app.toast.transaction_deleted')]);

        return $household !== null
            ? to_route('households.economy.index', $household)
            : to_route('economy.me.index');
    }

    /**
     * Resolve the household from the route, if present.
     *
     * @param  Request  $request  The incoming request containing the optional household route parameter.
     * @return Household|null The routed household, or null for a private economy route.
     */
    /**
     * Resolve the accounts the authenticated user may assign to a transaction.
     *
     * Only the owner sees their accounts, which keeps the funding source private even when the
     * transaction itself is shared with the household.
     *
     * @param  Request  $request  The incoming HTTP request.
     * @return array<int, array<string, mixed>> The serialized active accounts available to the user.
     */
    private function availableAccounts(Request $request): array
    {
        $user = $this->authenticatedUser($request);

        return EconomicAccount::query()
            ->ownedBy($user->id)
            ->active()
            ->with('paymentMethods')
            ->orderBy('name')
            ->get()
            ->map(fn (EconomicAccount $account): array => (new EconomicAccountResource($account))->resolve($request))
            ->all();
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
     * Ensure that a resource belongs to the household represented by the current route.
     *
     * @param  Request  $request  The incoming request containing the optional household route parameter.
     * @param  string|null  $resourceHouseholdId  The household identifier stored on the routed resource.
     * @return void This guard does not return a value.
     */
    private function ensureRouteScope(Request $request, ?string $resourceHouseholdId): void
    {
        abort_unless($resourceHouseholdId === $this->resolveHousehold($request)?->id, 403);
    }

    /**
     * Redirect to the transaction page in the right scope.
     *
     * @param  Household|null  $household  The household scope, or null for a private transaction.
     * @param  EconomicTransaction  $transaction  The transaction whose detail page should be displayed.
     * @return RedirectResponse The redirect response to the appropriate transaction detail route.
     */
    private function redirectToTransaction(?Household $household, EconomicTransaction $transaction): RedirectResponse
    {
        return $household !== null
            ? to_route('households.economy.show', [$household, $transaction])
            : to_route('economy.me.show', $transaction);
    }
}
