<?php

namespace App\Http\Controllers\Web;

use App\Actions\Economy\GetAccountBalances;
use App\Actions\Economy\GetHouseholdEconomyOverview;
use App\Actions\Economy\GetHouseholdsComparison;
use App\Actions\Economy\GetPersonalEconomyOverview;
use App\Actions\Economy\ListEconomicImports;
use App\Actions\Economy\ListEconomicTransactions;
use App\Actions\Households\AcceptHouseholdInvitation;
use App\Actions\Households\AcceptInvitation;
use App\Actions\Households\CancelInvitation;
use App\Actions\Households\CreateHousehold;
use App\Actions\Households\CreateHouseholdInviteLink;
use App\Actions\Households\DeleteHousehold;
use App\Actions\Households\GetHousehold;
use App\Actions\Households\GetHouseholdDashboard;
use App\Actions\Households\GetHouseholdSettings;
use App\Actions\Households\InviteMember;
use App\Actions\Households\ListHouseholds;
use App\Actions\Households\ListPendingInvitations;
use App\Actions\Households\RemoveMember;
use App\Actions\Households\RevokeHouseholdInviteLink;
use App\Actions\Households\SwitchActiveHousehold;
use App\Actions\Households\UpdateHouseholdSettings;
use App\Data\Households\CreateHouseholdData;
use App\Data\Households\CreateInviteLinkData;
use App\Data\Households\InviteMemberData;
use App\Data\Households\UpdateHouseholdSettingsData;
use App\Enums\EconomicImportStatus;
use App\Enums\HouseholdModule;
use App\Http\Controllers\Controller;
use App\Http\Requests\CreateHouseholdInviteLinkRequest;
use App\Http\Requests\InviteMemberRequest;
use App\Http\Requests\StoreHouseholdRequest;
use App\Http\Requests\UpdateHouseholdSettingsRequest;
use App\Http\Resources\Economy\EconomicAccountResource;
use App\Http\Resources\Economy\EconomicTransactionResource;
use App\Http\Resources\Households\HouseholdInvitationResource;
use App\Http\Resources\Households\HouseholdInviteLinkResource;
use App\Http\Resources\Households\HouseholdMemberResource;
use App\Http\Resources\Households\HouseholdResource;
use App\Models\EconomicAccount;
use App\Models\Household;
use App\Models\HouseholdInvitation;
use App\Models\HouseholdInviteLink;
use App\Models\HouseholdMember;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Handles household management, invitations and settings.
 */
final class HouseholdController extends Controller
{
    /**
     * The number of recent transactions shown on the dashboard.
     */
    private const RECENT_TRANSACTIONS = 6;

    /**
     * Show the household selection page with pending invitations.
     *
     * @param  ListHouseholds  $action  The action responsible for the operation.
     * @param  ListPendingInvitations  $pendingAction  The pendingAction value.
     * @param  Request  $request  The incoming HTTP request.
     * @return Response The HTTP response.
     */
    public function select(ListHouseholds $action, ListPendingInvitations $pendingAction, Request $request): Response
    {
        $user = $this->authenticatedUser($request);
        $households = $action->execute($user);
        $pendingInvitations = $pendingAction->execute($user);

        return Inertia::render('households/Select', [
            'households' => HouseholdResource::collection($households)->resolve(),
            'pendingInvitations' => HouseholdInvitationResource::collection($pendingInvitations)->resolve(),
        ]);
    }

    /**
     * List the user's households.
     *
     * @param  ListHouseholds  $action  The action responsible for the operation.
     * @param  Request  $request  The incoming HTTP request.
     * @return Response The HTTP response.
     */
    public function index(ListHouseholds $action, Request $request): Response
    {
        $households = $action->execute($this->authenticatedUser($request));

        return Inertia::render('households/Index', [
            'households' => HouseholdResource::collection($households)->resolve(),
        ]);
    }

    /**
     * Show the household creation form.
     *
     * @return Response The HTTP response.
     */
    public function create(): Response
    {
        $modulesConfig = $this->modulesConfig();
        $predefinedTags = $this->predefinedTags();

        $modules = collect($modulesConfig)->map(fn (array $config, string $key): array => [
            'module' => $key,
            'label' => $config['label'],
            'description' => $config['description'],
            'icon' => $config['icon'],
            'enabled' => true,
        ])->values()->all();

        $availableTags = collect($predefinedTags)->map(fn (string $slug, string $name): array => [
            'id' => Str::slug($name),
            'name' => $name,
            'slug' => $slug,
            'type' => 'predefined',
        ])->values()->all();

        return Inertia::render('households/Create', [
            'modules' => $modules,
            'tags' => [],
            'available_tags' => $availableTags,
        ]);
    }

    /**
     * Show the unified dashboard for the active household, or the personal one without a household.
     *
     * The dashboard lives at a single URL: the active household is resolved server-side instead of
     * being encoded in the path, so switching household no longer means switching dashboards.
     *
     * @param  Request  $request  The incoming HTTP request.
     * @param  GetHouseholdDashboard  $dashboard  The dashboard summary action.
     * @param  GetHouseholdEconomyOverview  $householdEconomy  The household economy overview action.
     * @param  GetPersonalEconomyOverview  $personalEconomy  The personal economy overview action.
     * @return Response The HTTP response.
     */
    public function redirectToActive(
        Request $request,
        GetHouseholdDashboard $dashboard,
        GetHouseholdEconomyOverview $householdEconomy,
        GetPersonalEconomyOverview $personalEconomy,
    ): RedirectResponse|Response {
        $user = $this->authenticatedUser($request);

        if (! $user->households_enabled) {
            return $this->renderPersonalDashboard($user, $personalEconomy);
        }

        $household = $user->active_household_id
            ? $user->households()->find($user->active_household_id)
            : null;

        if (! $household) {
            if ($user->active_household_id) {
                $user->update(['active_household_id' => null]);
            }

            // A user with households but no active one still has to pick where to stand.
            if ($user->households()->exists()) {
                return redirect()->route('households.select');
            }

            return $this->renderPersonalDashboard($user, $personalEconomy);
        }

        return $this->renderHouseholdDashboard($household, $user, $dashboard, $householdEconomy, $personalEconomy);
    }

    /**
     * Render the dashboard without a household context.
     *
     * @param  User  $user  The authenticated user the personal dashboard belongs to.
     * @param  GetPersonalEconomyOverview  $personalEconomy  The personal economy overview action.
     * @return Response The Inertia personal dashboard response.
     */
    private function renderPersonalDashboard(User $user, GetPersonalEconomyOverview $personalEconomy): Response
    {
        $permissions = $user->getPermissionRouteNames();
        $canViewPersonalEconomy = $permissions->contains('economy.me.index');

        $props = [
            'dashboard' => null,
            'household' => null,
            'can' => [
                'householdEconomy' => false,
                'personalEconomy' => $canViewPersonalEconomy,
                'createHouseholdExpense' => false,
                'importHouseholdTicket' => false,
                'createPersonalExpense' => $permissions->contains('economy.me.store'),
                'importPersonalTicket' => $permissions->contains('economy.me.create'),
                'accounts' => $permissions->contains('economy.me.accounts.index'),
                'lists' => false,
                'recipes' => false,
                'contacts' => $permissions->contains('contacts.view'),
            ],
        ];

        if ($canViewPersonalEconomy) {
            $props['personalEconomy'] = Inertia::defer(
                fn (): array => $personalEconomy->execute($user)
            );
            $props['householdComparison'] = Inertia::defer(
                fn (): array => (new GetHouseholdsComparison)->execute($user)
            );
            $props['personalAccounts'] = Inertia::defer(
                fn (): array => $this->personalAccountsPayload($user)
            );
            $props['recentTransactions'] = Inertia::defer(
                fn (): array => $this->recentTransactionsPayload($user)
            );
            $props['pendingImports'] = Inertia::defer(fn (): array => [
                'personal' => $this->pendingImportsCount($user),
                'household' => 0,
                'failed_personal' => $this->failedImportsCount($user),
                'failed_household' => 0,
            ]);
        }

        return Inertia::render('households/Dashboard', $props);
    }

    /**
     * Build the account balances payload shown on the dashboard.
     *
     * @param  User  $user  The user whose accounts are summarized.
     * @return array{total_minor: int, accounts: list<array<string, mixed>>} The account balances and their total.
     */
    private function personalAccountsPayload(User $user): array
    {
        $accounts = EconomicAccount::query()
            ->ownedBy($user->id)
            ->active()
            ->with('paymentMethods')
            ->orderBy('name')
            ->get();

        $balances = (new GetAccountBalances)->execute($user, $accounts);
        $included = $accounts->filter(fn (EconomicAccount $account): bool => $account->include_in_totals);

        return [
            'total_minor' => (int) $included->sum(fn (EconomicAccount $account): int => $balances[$account->id] ?? 0),
            'accounts' => $accounts
                ->map(fn (EconomicAccount $account): array => (new EconomicAccountResource($account))
                    ->additional(['balance_minor' => $balances[$account->id] ?? 0])
                    ->resolve())
                ->values()
                ->all(),
        ];
    }

    /**
     * Build the most recent visible transactions payload shown on the dashboard.
     *
     * @param  User  $user  The user whose recent movements are listed.
     * @return array{items: list<array<string, mixed>>, total: int} The recent transactions and their overall count.
     */
    private function recentTransactionsPayload(User $user): array
    {
        $recent = (new ListEconomicTransactions)->executePersonal($user, ['perPage' => self::RECENT_TRANSACTIONS]);

        return [
            'items' => EconomicTransactionResource::collection($recent->getCollection())->resolve(),
            'total' => $recent->total(),
        ];
    }

    /**
     * Resolve the count of imports that are waiting for the AI analysis or the user review.
     *
     * @param  User  $user  The user whose imports are counted.
     * @param  Household|null  $household  The household scope, or null for the private scope.
     * @return int The number of in-progress imports.
     */
    private function pendingImportsCount(User $user, ?Household $household = null): int
    {
        return (new ListEconomicImports)->countByStatuses($user, [
            EconomicImportStatus::Pending,
            EconomicImportStatus::Processing,
            EconomicImportStatus::ReadyForReview,
        ], $household);
    }

    /**
     * Resolve the count of imports whose analysis failed in the given scope.
     *
     * @param  User  $user  The user whose imports are counted.
     * @param  Household|null  $household  The household scope, or null for the private scope.
     * @return int The number of failed imports.
     */
    private function failedImportsCount(User $user, ?Household $household = null): int
    {
        return (new ListEconomicImports)->countByStatuses($user, [
            EconomicImportStatus::Failed,
        ], $household);
    }

    /**
     * Render the dashboard for a concrete household.
     *
     * @param  Household  $household  The household to summarize.
     * @param  User  $user  The authenticated user viewing the dashboard.
     * @param  GetHouseholdDashboard  $action  The dashboard summary action.
     * @param  GetHouseholdEconomyOverview  $householdEconomy  The household economy overview action.
     * @param  GetPersonalEconomyOverview  $personalEconomy  The cross-household personal economy overview action.
     * @return Response The Inertia household dashboard response.
     */
    private function renderHouseholdDashboard(
        Household $household,
        User $user,
        GetHouseholdDashboard $action,
        GetHouseholdEconomyOverview $householdEconomy,
        GetPersonalEconomyOverview $personalEconomy,
    ): Response {
        $permissions = $user->getPermissionRouteNames();
        $enabledModules = $household->modules()
            ->where('enabled', true)
            ->pluck('module')
            ->all();
        $economyModuleEnabled = in_array(HouseholdModule::Economy->value, $enabledModules, true);
        $shoppingListsModuleEnabled = in_array(HouseholdModule::ShoppingLists->value, $enabledModules, true);
        $recipesModuleEnabled = in_array(HouseholdModule::Recipes->value, $enabledModules, true);
        $canViewHouseholdEconomy = $economyModuleEnabled
            && $permissions->contains('households.economy.index');
        $canViewPersonalEconomy = $permissions->contains('economy.me.index');

        $props = [
            'dashboard' => Inertia::defer(
                fn (): array => $action->execute($household, $user)
            ),
            'household' => $household,
            'can' => [
                'householdEconomy' => $canViewHouseholdEconomy,
                'personalEconomy' => $canViewPersonalEconomy,
                'createHouseholdExpense' => $economyModuleEnabled && $permissions->contains('economy.create'),
                'importHouseholdTicket' => $economyModuleEnabled && $permissions->contains('economy.imports.create'),
                'createPersonalExpense' => $permissions->contains('economy.me.store'),
                'importPersonalTicket' => $permissions->contains('economy.me.create'),
                'accounts' => $permissions->contains('economy.me.accounts.index'),
                'lists' => $shoppingListsModuleEnabled && $permissions->contains('households.lists.index'),
                'recipes' => $recipesModuleEnabled && $permissions->contains('households.recipes.index'),
                // Contacts is a global module (not per-household), so only the permission gates it.
                'contacts' => $permissions->contains('contacts.view'),
            ],
        ];

        if ($canViewHouseholdEconomy) {
            $props['householdEconomy'] = Inertia::defer(
                fn (): array => $householdEconomy->execute($household, $user)
            );
        }

        if ($canViewPersonalEconomy) {
            $props['personalEconomy'] = Inertia::defer(
                fn (): array => $personalEconomy->execute($user)
            );
            $props['householdComparison'] = Inertia::defer(
                fn (): array => (new GetHouseholdsComparison)->execute($user)
            );
            $props['personalAccounts'] = Inertia::defer(
                fn (): array => $this->personalAccountsPayload($user)
            );
            $props['recentTransactions'] = Inertia::defer(
                fn (): array => $this->recentTransactionsPayload($user)
            );
            $props['pendingImports'] = Inertia::defer(fn (): array => [
                'personal' => $this->pendingImportsCount($user),
                'household' => $economyModuleEnabled
                    ? $this->pendingImportsCount($user, $household)
                    : 0,
                'failed_personal' => $this->failedImportsCount($user),
                'failed_household' => $economyModuleEnabled
                    ? $this->failedImportsCount($user, $household)
                    : 0,
            ]);
        }

        return Inertia::render('households/Dashboard', $props);
    }

    /**
     * Create a household and optionally apply its settings.
     *
     * @param  StoreHouseholdRequest  $request  The incoming HTTP request.
     * @param  CreateHousehold  $action  The action responsible for the operation.
     * @param  UpdateHouseholdSettings  $settingsAction  The settingsAction value.
     * @param  GetHouseholdSettings  $getSettings  The getSettings value.
     * @return Response The HTTP response.
     */
    public function store(StoreHouseholdRequest $request, CreateHousehold $action, UpdateHouseholdSettings $settingsAction, GetHouseholdSettings $getSettings): Response
    {
        $validated = $request->validated();

        // 1. Create the household with basic fields
        $householdData = CreateHouseholdData::fromArray($validated);
        $household = $action->execute($householdData, $this->authenticatedUser($request));

        // 2. Apply settings (image, modules, tags) if provided
        $hasSettings = $request->hasFile('image')
            || $request->has('modules')
            || $request->has('tags');

        if ($hasSettings) {
            $settingsData = new UpdateHouseholdSettingsData(
                name: $validated['name'],
                description: $validated['description'] ?? null,
                color: $validated['color'] ?? null,
                image: $request->file('image'),
                removeImage: (bool) ($validated['remove_image'] ?? false),
                modules: $validated['modules'] ?? null,
                tags: $validated['tags'] ?? null,
            );

            $settingsAction->execute($household, $settingsData);
        }

        // 3. Render Wizard step 2 (AI configuration) with full household data
        $settings = $getSettings->execute($household);

        return $this->renderWizard($household, $settings);
    }

    /**
     * Render the Wizard step 2 page with providers data.
     *
     * @param  array<string, mixed>  $settings
     */
    private function renderWizard(Household $household, array $settings): Response
    {
        $providers = $household->aiProviders()->get()->map(fn ($p) => [
            'id' => $p->id,
            'name' => $p->name,
            'type' => $p->type,
            'base_url' => $p->base_url,
            'model' => $p->model,
            'module' => $p->module,
            'modules' => $p->assignedModules(),
            'default_modules' => $p->defaultModules(),
            'module_label' => $p->module,
            'enabled' => $p->enabled,
            'is_default' => $p->is_default,
            'configuration' => $p->configuration,
        ]);

        return Inertia::render('households/Wizard', [
            'household' => $settings['household'],
            'modules' => $settings['modules'],
            'tags' => $settings['tags'],
            'available_tags' => $settings['available_tags'],
            'providers' => $providers,
        ]);
    }

    /**
     * Show a household's detail page.
     *
     * @param  Household  $household  The household model instance.
     * @param  GetHousehold  $action  The action responsible for the operation.
     * @return Response The HTTP response.
     */
    public function show(Household $household, GetHousehold $action): Response
    {
        $this->authorize('view', $household);

        $householdData = $action->execute($household);

        return Inertia::render('households/Show', [
            'household' => [
                'id' => $householdData->id,
                'name' => $householdData->name,
                'invite_code' => $householdData->invite_code,
                'created_by' => $householdData->created_by,
                'members' => HouseholdMemberResource::collection($householdData->members)->resolve(),
                'pending_invitations' => HouseholdInvitationResource::collection($householdData->pendingInvitations)->resolve(),
            ],
        ]);
    }

    /**
     * Delete a household.
     *
     * @param  Household  $household  The household model instance.
     * @param  DeleteHousehold  $action  The action responsible for the operation.
     * @return RedirectResponse The HTTP response.
     */
    public function destroy(Household $household, DeleteHousehold $action): RedirectResponse
    {
        $this->authorize('delete', $household);
        $action->execute($household);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Hogar eliminado correctamente.']);

        return to_route('households.index');
    }

    /**
     * Invite a member to the household.
     *
     * @param  InviteMemberRequest  $request  The incoming HTTP request.
     * @param  Household  $household  The household model instance.
     * @param  InviteMember  $action  The action responsible for the operation.
     * @return RedirectResponse The HTTP response.
     */
    public function invite(InviteMemberRequest $request, Household $household, InviteMember $action): RedirectResponse
    {
        $data = InviteMemberData::fromArray($request->validated());
        $action->execute($data, $household, $this->authenticatedUser($request));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('app.toast.invitation_sent')]);

        return back();
    }

    /**
     * Accept a household invitation by token.
     *
     * @param  string  $token  The invitation token.
     * @param  AcceptInvitation  $action  The action responsible for the operation.
     * @param  Request  $request  The incoming HTTP request.
     * @return RedirectResponse The HTTP response.
     */
    public function acceptInvitation(string $token, AcceptInvitation $action, Request $request): RedirectResponse
    {
        $action->execute($token, $this->authenticatedUser($request));

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Te has unido al hogar correctamente.']);

        return to_route('households.index');
    }

    /**
     * Accept a pending household invitation from the invitations list.
     *
     * @param  HouseholdInvitation  $invitation  The household invitation model instance.
     * @param  AcceptHouseholdInvitation  $action  The action responsible for the operation.
     * @param  Request  $request  The incoming HTTP request.
     * @return RedirectResponse The HTTP response.
     */
    public function acceptFromList(HouseholdInvitation $invitation, AcceptHouseholdInvitation $action, Request $request): RedirectResponse
    {
        $action->execute($invitation, $this->authenticatedUser($request));

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Te has unido al hogar correctamente.']);

        return to_route('dashboard');
    }

    /**
     * Cancel a household invitation.
     *
     * @param  HouseholdInvitation  $invitation  The household invitation model instance.
     * @param  CancelInvitation  $action  The action responsible for the operation.
     * @param  Request  $request  The incoming HTTP request.
     * @return RedirectResponse The HTTP response.
     */
    public function cancelInvitation(HouseholdInvitation $invitation, CancelInvitation $action, Request $request): RedirectResponse
    {
        $action->execute($invitation, $this->authenticatedUser($request));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('app.toast.invitation_cancelled')]);

        return back();
    }

    /**
     * Switch the user's active household.
     *
     * @param  Household  $household  The household model instance.
     * @param  SwitchActiveHousehold  $action  The action responsible for the operation.
     * @param  Request  $request  The incoming HTTP request.
     * @return RedirectResponse The HTTP response.
     */
    public function switchActive(Household $household, SwitchActiveHousehold $action, Request $request): RedirectResponse
    {
        $action->execute($household, $this->authenticatedUser($request));

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Hogar activo cambiado.']);

        return to_route('dashboard');
    }

    /**
     * Remove a member from the household.
     *
     * @param  Household  $household  The household model instance.
     * @param  HouseholdMember  $member  The household member model instance.
     * @param  RemoveMember  $action  The action responsible for the operation.
     * @return RedirectResponse The HTTP response.
     */
    public function removeMember(Household $household, HouseholdMember $member, RemoveMember $action): RedirectResponse
    {
        $this->authorize('removeMember', [$household, $member]);
        $action->execute($household, $member);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Miembro eliminado del hogar.']);

        return back();
    }

    /**
     * Link or unlink a household member with one of the viewer's contacts.
     *
     * @param  Request  $request  The incoming HTTP request with the contact id.
     * @param  Household  $household  The household model instance.
     * @param  HouseholdMember  $member  The household member model instance.
     * @return RedirectResponse The HTTP response.
     */
    public function linkMemberContact(Request $request, Household $household, HouseholdMember $member): RedirectResponse
    {
        $this->authorize('view', $household);

        $user = $this->authenticatedUser($request);
        abort_unless($member->household_id === $household->id, 404);
        abort_unless($household->isAdmin($user) || $member->user_id === $user->id, 403);

        $validated = $request->validate([
            'contact_id' => ['nullable', 'uuid', Rule::exists('contacts', 'id')->where(fn ($query) => $query
                ->where('user_id', $user->id)
                ->orWhereIn('household_id', DB::table('household_members')->where('user_id', $user->id)->select('household_id')))],
        ]);

        $member->update(['contact_id' => $validated['contact_id'] ?? null]);

        return back();
    }

    /**
     * Create a reusable invite link for the household.
     *
     * @param  CreateHouseholdInviteLinkRequest  $request  The incoming HTTP request.
     * @param  Household  $household  The household model instance.
     * @param  CreateHouseholdInviteLink  $action  The action responsible for the operation.
     * @return RedirectResponse The HTTP response.
     */
    public function storeInviteLink(CreateHouseholdInviteLinkRequest $request, Household $household, CreateHouseholdInviteLink $action): RedirectResponse
    {
        $this->authorize('update', $household);

        $action->execute(CreateInviteLinkData::fromArray($request->validated()), $household, $request->user());

        return back();
    }

    /**
     * Revoke a reusable invite link of the household.
     *
     * @param  Household  $household  The household model instance.
     * @param  HouseholdInviteLink  $link  The invite link to revoke.
     * @param  RevokeHouseholdInviteLink  $action  The action responsible for the operation.
     * @return RedirectResponse The HTTP response.
     */
    public function destroyInviteLink(Household $household, HouseholdInviteLink $link, RevokeHouseholdInviteLink $action): RedirectResponse
    {
        $this->authorize('update', $household);
        abort_unless($link->household_id === $household->id, 404);

        $action->execute($link);

        return back();
    }

    /**
     * Show the household settings editing page.
     *
     * @param  Household  $household  The household model instance.
     * @param  GetHouseholdSettings  $action  The action responsible for the operation.
     * @return Response The HTTP response.
     */
    public function editSettings(Household $household, GetHouseholdSettings $action): Response
    {
        $this->authorize('update', $household);

        return Inertia::render('households/settings/Edit', array_merge($action->execute($household), [
            'invite_links' => HouseholdInviteLinkResource::collection(
                $household->inviteLinks()->orderByDesc('created_at')->get()
            )->resolve(),
        ]));
    }

    /**
     * Update the household settings.
     *
     * @param  UpdateHouseholdSettingsRequest  $request  The incoming HTTP request.
     * @param  Household  $household  The household model instance.
     * @param  UpdateHouseholdSettings  $action  The action responsible for the operation.
     * @return RedirectResponse The HTTP response.
     */
    public function updateSettings(UpdateHouseholdSettingsRequest $request, Household $household, UpdateHouseholdSettings $action): RedirectResponse
    {
        $validated = $request->validated();

        $data = new UpdateHouseholdSettingsData(
            name: $validated['name'],
            description: $validated['description'] ?? null,
            color: $validated['color'] ?? null,
            image: $request->file('image'),
            removeImage: (bool) ($validated['remove_image'] ?? false),
            modules: $validated['modules'] ?? null,
            tags: $validated['tags'] ?? null,
            defaultSplitType: $validated['default_split_type'] ?? null,
        );

        $result = $action->execute($household, $data);

        $toastMessage = __('app.toast.household_settings_updated');
        if (! empty($result['warnings'])) {
            $warningCount = count($result['warnings']);
            $toastMessage .= " Hay {$warningCount} advertencia(s) de módulos.";
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => $toastMessage]);
        Inertia::flash('warnings', $result['warnings']);

        return to_route('households.settings.edit', $household);
    }

    /**
     * Show the household wizard page.
     *
     * @param  Household  $household  The household model instance.
     * @param  GetHouseholdSettings  $settingsAction  The settingsAction value.
     * @return Response The HTTP response.
     */
    public function wizard(Household $household, GetHouseholdSettings $settingsAction): Response
    {
        $this->authorize('update', $household);

        $settings = $settingsAction->execute($household);

        return Inertia::render('households/Wizard', [
            'household' => $settings['household'],
            'modules' => $settings['modules'],
            'tags' => $settings['tags'],
            'available_tags' => $settings['available_tags'],
        ]);
    }

    /**
     * Complete the wizard and redirect to the dashboard.
     *
     * @param  Household  $household  The household model instance.
     * @return RedirectResponse The HTTP response.
     */
    public function wizardComplete(Household $household): RedirectResponse
    {
        $this->authorize('update', $household);

        return to_route('dashboard');
    }

    /**
     * Read the household modules configuration.
     *
     * @return array<string, array{label: string, description: string, icon: string}>
     */
    private function modulesConfig(): array
    {
        return Config::array('household_modules.modules');
    }

    /**
     * Read the predefined household tags configuration.
     *
     * @return array<string, string>
     */
    private function predefinedTags(): array
    {
        return Config::array('household_modules.predefined_tags');
    }
}
