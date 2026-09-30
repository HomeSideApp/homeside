<?php

namespace App\Http\Controllers\Web;

use App\Actions\Economy\ArchiveEconomicAccount;
use App\Actions\Economy\CreateEconomicAccount;
use App\Actions\Economy\DeleteEconomicAccount;
use App\Actions\Economy\GetAccountBalances;
use App\Actions\Economy\ListEconomicTransactions;
use App\Actions\Economy\UpdateEconomicAccount;
use App\Data\Economy\EconomicAccountData;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreEconomicAccountRequest;
use App\Http\Requests\UpdateEconomicAccountRequest;
use App\Http\Resources\Economy\EconomicAccountResource;
use App\Http\Resources\Economy\EconomicTransactionResource;
use App\Http\Resources\Economy\PaymentMethodResource;
use App\Models\CryptoAsset;
use App\Models\EconomicAccount;
use App\Models\PaymentMethod;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Class EconomicAccountController
 *
 * Handles the private account pages of the economy module. Accounts are always scoped to the
 * authenticated user and can never be reached through a household route.
 */
final class EconomicAccountController extends Controller
{
    /**
     * List the authenticated user's economic accounts with their computed balances.
     *
     * @param  Request  $request  The incoming HTTP request, optionally containing search filters.
     * @param  GetAccountBalances  $balances  The action that resolves the account balances in one query.
     * @return Response The Inertia accounts list response.
     */
    public function index(Request $request, GetAccountBalances $balances): Response
    {
        $user = $this->authenticatedUser($request);
        $this->authorize('viewAny', EconomicAccount::class);

        $query = EconomicAccount::query()
            ->ownedBy($user->id)
            ->with('paymentMethods')
            ->when($request->input('search'), fn ($builder, $search) => $builder
                ->where('name', 'like', '%'.$search.'%'))
            ->when($request->input('currency'), fn ($builder, $currency) => $builder
                ->where('currency', strtoupper((string) $currency)))
            // The account-to-method relation is many-to-many, so filtering must go through the
            // pivot instead of the legacy single `payment_method_id` column.
            ->when($request->input('payment_method_id'), fn ($builder, $methodId) => $builder
                ->whereHas('paymentMethods', fn ($methodQuery) => $methodQuery
                    ->whereKey($methodId)))
            ->when(
                $request->input('include_archived') !== '1',
                fn ($builder) => $builder->active(),
            )
            ->orderBy('archived_at')
            ->orderBy('name');

        $accounts = $query->get();
        $resolvedBalances = $balances->execute($user, $accounts);

        return Inertia::render('economy/Accounts/Index', [
            'accounts' => $accounts->map(fn (EconomicAccount $account): array => (new EconomicAccountResource($account))
                ->additional(['balance_minor' => $resolvedBalances[$account->id] ?? 0])
                ->resolve($request))->all(),
            'filters' => $request->only(['search', 'currency', 'payment_method_id', 'include_archived']),
            'paymentMethods' => PaymentMethodResource::collection(
                PaymentMethod::query()->visibleTo($user->id)->active()->orderBy('sort_order')->get()
            )->resolve($request),
            'totals' => [
                'balance_minor' => collect($resolvedBalances)->sum(),
                'accounts_count' => $accounts->count(),
            ],
        ]);
    }

    /**
     * Show the account creation form.
     *
     * @param  Request  $request  The incoming HTTP request.
     * @return Response The Inertia account creation response.
     */
    public function create(Request $request): Response
    {
        $user = $this->authenticatedUser($request);
        $this->authorize('create', EconomicAccount::class);

        return Inertia::render('economy/Accounts/Create', [
            'paymentMethods' => $this->availablePaymentMethods($request),
            'cryptoAssets' => $this->cryptoAssets(),
        ]);
    }

    /**
     * Persist a new private account and redirect to its detail page.
     *
     * @param  StoreEconomicAccountRequest  $request  The authorized and validated account request.
     * @param  CreateEconomicAccount  $action  The action used to persist the account.
     * @return RedirectResponse The redirect response to the created account.
     */
    public function store(StoreEconomicAccountRequest $request, CreateEconomicAccount $action): RedirectResponse
    {
        $data = EconomicAccountData::fromArray($request->validated());
        $account = $action->execute($data, $this->authenticatedUser($request));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('app.toast.account_created')]);

        return to_route('economy.me.accounts.show', $account);
    }

    /**
     * Display an account together with the transactions assigned to it.
     *
     * @param  Request  $request  The incoming HTTP request.
     * @param  EconomicAccount  $account  The routed account model instance.
     * @param  GetAccountBalances  $balances  The action that resolves the account balance.
     * @param  ListEconomicTransactions  $transactions  The action that lists the account movements.
     * @return Response The Inertia account detail response.
     */
    public function show(
        Request $request,
        EconomicAccount $account,
        GetAccountBalances $balances,
        ListEconomicTransactions $transactions,
    ): Response {
        $this->authorize('view', $account);

        $user = $this->authenticatedUser($request);
        $resolvedBalances = $balances->execute($user, collect([$account]));

        $movements = $transactions->executePersonal($user, [
            'account_id' => $account->id,
            'perPage' => $request->input('perPage', 15),
        ]);

        return Inertia::render('economy/Accounts/Show', [
            'account' => (new EconomicAccountResource($account->load('paymentMethods')))
                ->additional(['balance_minor' => $resolvedBalances[$account->id] ?? 0])
                ->resolve($request),
            'transactions' => EconomicTransactionResource::collection($movements),
            'filters' => $request->only(['perPage']),
        ]);
    }

    /**
     * Show the account editing form.
     *
     * @param  Request  $request  The incoming HTTP request.
     * @param  EconomicAccount  $account  The routed account model instance.
     * @return Response The Inertia account edit response.
     */
    public function edit(Request $request, EconomicAccount $account): Response
    {
        $this->authorize('update', $account);
        $user = $this->authenticatedUser($request);

        return Inertia::render('economy/Accounts/Edit', [
            'account' => (new EconomicAccountResource($account->load(['paymentMethods', 'cryptoAsset'])))->resolve($request),
            'paymentMethods' => $this->availablePaymentMethods($request),
            'cryptoAssets' => $this->cryptoAssets(),
        ]);
    }

    /**
     * Apply the validated changes to an account and redirect to its detail page.
     *
     * @param  UpdateEconomicAccountRequest  $request  The authorized and validated update request.
     * @param  EconomicAccount  $account  The routed account model instance.
     * @param  UpdateEconomicAccount  $action  The action used to persist the update.
     * @return RedirectResponse The redirect response to the updated account.
     */
    public function update(UpdateEconomicAccountRequest $request, EconomicAccount $account, UpdateEconomicAccount $action): RedirectResponse
    {
        $data = EconomicAccountData::fromArray($request->validated());
        $action->execute($account, $data);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('app.toast.account_updated')]);

        return to_route('economy.me.accounts.show', $account);
    }

    /**
     * Toggle the archived state of an account.
     *
     * @param  Request  $request  The incoming HTTP request carrying the desired archived flag.
     * @param  EconomicAccount  $account  The routed account model instance.
     * @param  ArchiveEconomicAccount  $action  The action used to toggle the archived state.
     * @return RedirectResponse The redirect response back to the accounts list.
     */
    public function archive(Request $request, EconomicAccount $account, ArchiveEconomicAccount $action): RedirectResponse
    {
        $this->authorize('archive', $account);
        $archived = filter_var($request->input('archived', true), FILTER_VALIDATE_BOOL);
        $action->execute($account, $archived);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $archived
                ? __('app.toast.account_archived')
                : __('app.toast.account_restored'),
        ]);

        return to_route('economy.me.accounts.index');
    }

    /**
     * Delete an account and redirect to the accounts list.
     *
     * @param  EconomicAccount  $account  The routed account model instance.
     * @param  DeleteEconomicAccount  $action  The action used to delete the account.
     * @return RedirectResponse The redirect response back to the accounts list.
     */
    public function destroy(EconomicAccount $account, DeleteEconomicAccount $action): RedirectResponse
    {
        $this->authorize('delete', $account);
        $action->execute($account);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('app.toast.account_deleted')]);

        return to_route('economy.me.accounts.index');
    }

    /**
     * Resolve the payment methods available to the authenticated user.
     *
     * @param  Request  $request  The incoming HTTP request.
     * @return array<int, array<string, mixed>> The serialized active payment methods.
     */
    private function availablePaymentMethods(Request $request): array
    {
        $user = $this->authenticatedUser($request);

        return PaymentMethodResource::collection(
            PaymentMethod::query()->visibleTo($user->id)->active()->orderBy('sort_order')->get()
        )->resolve($request);
    }

    /**
     * Resolve the crypto assets that can back a crypto account.
     *
     * @return array<int, array<string, mixed>> The serialized active crypto assets.
     */
    private function cryptoAssets(): array
    {
        return CryptoAsset::query()->active()->orderBy('symbol')->get()
            ->map(fn (CryptoAsset $asset): array => [
                'id' => $asset->id,
                'symbol' => $asset->symbol,
                'name' => $asset->name,
                'decimal_places' => $asset->decimal_places,
            ])
            ->all();
    }
}
