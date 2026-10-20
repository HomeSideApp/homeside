<?php

namespace App\Providers;

use App\Contracts\AuthenticationBootstrapRepository;
use App\Contracts\CryptoPriceFetcher;
use App\Contracts\EntitlementGate;
use App\Contracts\RegistrationBlockRepository;
use App\Contracts\RegistrationGate;
use App\Contracts\RegistrationPolicyDefaults;
use App\Enums\AppLocale;
use App\Enums\ApprovalStatus;
use App\Enums\RegistrationAuditAction;
use App\Http\Middleware\SecureHttpMiddleware;
use App\Http\Requests\StoreRecipeApiRequest;
use App\Http\Resources\Assistant\AiProposalResource;
use App\Listeners\JobFailedListener;
use App\Listeners\JobProcessedListener;
use App\Listeners\JobProcessingListener;
use App\Models\Category;
use App\Models\Product;
use App\Models\RegistrationAudit;
use App\Models\Store;
use App\Models\Tag;
use App\Models\User;
use App\Notifications\RegistrationRequested;
use App\Observers\CategoryObserver;
use App\Observers\ProductObserver;
use App\Observers\StoreObserver;
use App\Policies\AiConversationPolicy;
use App\Policies\AiProviderPolicy;
use App\Policies\RegistrationApprovalPolicy;
use App\Policies\RegistrationSettingsPolicy;
use App\Services\Auth\SelfHostedRegistrationGate;
use App\Services\Auth\SelfHostedRegistrationPolicyDefaults;
use App\Services\Economy\CoinGeckoPriceFetcher;
use App\Support\Auth\AuthenticationBootstrapRepository as EloquentAuthenticationBootstrapRepository;
use App\Support\Auth\GoogleDriverRegistrar;
use App\Support\NullEntitlementGate;
use App\Support\OpenApi\DiscriminatedOneOfType;
use App\Support\Registration\RegistrationBlockRepository as EloquentRegistrationBlockRepository;
use Carbon\CarbonImmutable;
use Dedoc\Scramble\Scramble;
use Dedoc\Scramble\Support\Generator\OpenApi;
use Dedoc\Scramble\Support\Generator\Operation;
use Dedoc\Scramble\Support\Generator\Parameter as OpenApiParameter;
use Dedoc\Scramble\Support\Generator\Schema;
use Dedoc\Scramble\Support\Generator\Types\ArrayType;
use Dedoc\Scramble\Support\Generator\Types\NumberType;
use Dedoc\Scramble\Support\Generator\Types\ObjectType;
use Dedoc\Scramble\Support\Generator\Types\StringType;
use Dedoc\Scramble\Support\RouteInfo;
use HomeSide\AiAgents\Models\AiConversation;
use HomeSide\AiAgents\Models\AiProvider;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->configureEntitlements();
        $this->configureRegistrationDefaults();
        $this->configureRegistration();
        $this->configureRegistrationBlocks();
        $this->configureAuthenticationBootstraps();
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureLocale();
        $this->configureDefaults();
        $this->configureRateLimiting();
        $this->configureObservers();
        $this->configureHttpMacros();
        $this->configureMorphMap();
        $this->configureOpenApi();
        $this->configureAiPolicies();
        $this->configureRegistrationPolicies();
        $this->configureRegistrationNotifications();
        $this->configureCryptoPrices();
        $this->configureJobMonitoring();
        $this->configureGoogleProvider();
    }

    /**
     * Point Socialite's Google driver at the E2E simulator, inside E2E only.
     *
     * The registrar is fail-closed: outside the E2E environment it registers
     * nothing, so a production install keeps the framework's real Google
     * driver even if the simulator flag is set. Only the three provider
     * endpoints move; state validation, the redirect-URI check and the code
     * exchange stay in Socialite's real code paths.
     */
    protected function configureGoogleProvider(): void
    {
        app(GoogleDriverRegistrar::class)->register();
    }

    /**
     * Track queue job executions when job monitoring is enabled.
     */
    protected function configureJobMonitoring(): void
    {
        if (! config('job-monitoring.enabled', true)) {
            return;
        }

        Event::listen(JobProcessing::class, JobProcessingListener::class);
        Event::listen(JobProcessed::class, JobProcessedListener::class);
        Event::listen(JobFailed::class, JobFailedListener::class);
    }

    /**
     * Bind the crypto market price provider implementation.
     *
     * The contract keeps the valuation decoupled from the market provider, so tests can fake the
     * fetcher without touching the network.
     */
    protected function configureCryptoPrices(): void
    {
        $this->app->bind(CryptoPriceFetcher::class, CoinGeckoPriceFetcher::class);
    }

    /**
     * Bind the entitlement gate behind its app-owned port.
     *
     * Community owns the contract and its null fallback: a self-hosted install has nothing to
     * meter, so every check passes. Using `bindIf` instead of `bind` makes the registration
     * order-independent. Package-discovered providers register before the app's own providers, so
     * when a commercial package is installed its `bind` runs first and this `bindIf` yields; if the
     * order were ever reversed, this `bindIf` would register the null gate and the package's `bind`
     * would overwrite it. Either way the package wins, which is why Community never needs to know
     * whether that package exists.
     */
    protected function configureEntitlements(): void
    {
        $this->app->bindIf(EntitlementGate::class, NullEntitlementGate::class);
    }

    /**
     * Bind the fallback registration policy behind its app-owned port.
     *
     * Community owns the contract and its self-hosted defaults: a fresh install
     * starts closed so nobody can register over the network until the operator
     * asks for it. Using `bindIf` (NOT `bind`) keeps the provider registration
     * order-independent. Package-discovered providers register BEFORE the
     * application's own providers, so when a commercial package installs its
     * own defaults its `bind` runs first and this `bindIf` yields; if the order
     * were ever reversed, this `bindIf` would register the self-hosted defaults
     * and the package's later `bind` would still overwrite them. Either way the
     * package wins, so Community never needs to know whether that package
     * exists. Do not "fix" this to a plain `bind`: that would run last and
     * silently overwrite the commercial defaults.
     *
     * The binding is deliberately not shared, matching RegistrationSettings:
     * every fresh resolution must observe the current binding.
     */
    protected function configureRegistrationDefaults(): void
    {
        $this->app->bindIf(RegistrationPolicyDefaults::class, SelfHostedRegistrationPolicyDefaults::class);
    }

    /**
     * Bind the registration gate behind its app-owned port.
     *
     * Community owns the contract and its self-hosted implementation: the gate
     * only reads the local per-channel policy. Using `bindIf` keeps the
     * registration order-independent, so a commercial package can override the
     * binding without Community referencing it. The SaaS package will supply its
     * own gate in a later phase.
     */
    protected function configureRegistration(): void
    {
        $this->app->bindIf(RegistrationGate::class, SelfHostedRegistrationGate::class);
    }

    /**
     * Bind the persistent registration block repository behind its contract.
     */
    protected function configureRegistrationBlocks(): void
    {
        $this->app->bind(RegistrationBlockRepository::class, EloquentRegistrationBlockRepository::class);
    }

    /**
     * Bind the single-use authentication bootstrap repository behind its contract.
     *
     * The token is stored only as a keyed digest and consumed with a conditional
     * update, so the transport depends on the contract while the database is
     * the arbiter of single use.
     */
    protected function configureAuthenticationBootstraps(): void
    {
        $this->app->bind(AuthenticationBootstrapRepository::class, EloquentAuthenticationBootstrapRepository::class);
    }

    /**
     * Register the named abilities backed by RegistrationApprovalPolicy.
     *
     * The user model already owns UserPolicy, so the registration-approval
     * abilities are exposed as named gates instead of a second model policy.
     */
    protected function configureRegistrationPolicies(): void
    {
        Gate::define('registrations.view', fn (User $actor, ?User $target = null): bool => app(RegistrationApprovalPolicy::class)->view($actor, $target));
        Gate::define('registrations.approve', fn (User $actor, User $target): bool => app(RegistrationApprovalPolicy::class)->approve($actor, $target));
        Gate::define('registrations.reject', fn (User $actor, User $target): bool => app(RegistrationApprovalPolicy::class)->reject($actor, $target));
        Gate::define('registrations.release-rejection', fn (User $actor, User $target): bool => app(RegistrationApprovalPolicy::class)->releaseRejection($actor, $target));
        Gate::define('registrations.manage-settings', fn (User $actor, ?User $target = null): bool => app(RegistrationApprovalPolicy::class)->manageSettings($actor, $target));
        Gate::define('registration-settings.view', fn (User $actor): bool => app(RegistrationSettingsPolicy::class)->view($actor));
        Gate::define('registration-settings.update', fn (User $actor): bool => app(RegistrationSettingsPolicy::class)->update($actor));
        Gate::define('registration-blocks.release', fn (User $actor): bool => $actor->canAccessApplication()
            && $actor->getPermissionRouteNames()->contains('admin.registration-blocks.release'));
    }

    /**
     * Notify the administrators who can approve when a registration waits.
     *
     * The registration use case is deliberately unaware of notifications, so
     * the trigger is the audit entry it already writes. Dispatching after commit
     * means a mail failure can never roll the registration back, and the
     * notification can be retried without repeating the decision. No new audit
     * entry and no HTTP call is involved.
     */
    protected function configureRegistrationNotifications(): void
    {
        RegistrationAudit::created(function (RegistrationAudit $audit): void {
            if ($audit->action !== RegistrationAuditAction::RegistrationCreated) {
                return;
            }

            if ($audit->new_status !== ApprovalStatus::Pending->value) {
                return;
            }

            $applicantId = $audit->user_id;

            if ($applicantId === null) {
                return;
            }

            DB::afterCommit(function () use ($applicantId): void {
                $applicant = User::query()->find($applicantId);

                if ($applicant === null) {
                    return;
                }

                try {
                    RegistrationRequested::sendToAdministrators($applicant);
                } catch (\Throwable $exception) {
                    report($exception);
                }
            });
        });
    }

    /**
     * Register policies for the package's AI models: auto-discovery cannot
     * map HomeSide\AiAgents\Models\* to the host's App\Policies\* namespace.
     */
    protected function configureAiPolicies(): void
    {
        Gate::policy(AiConversation::class, AiConversationPolicy::class);
        Gate::policy(AiProvider::class, AiProviderPolicy::class);
    }

    /**
     * Register the morph map aliases for the translatable catalog models, so
     * the polymorphic translations tables store stable short aliases.
     */
    protected function configureMorphMap(): void
    {
        Relation::morphMap([
            'category' => Category::class,
            'product' => Product::class,
            'store' => Store::class,
            'tag' => Tag::class,
        ]);
    }

    protected function configureLocale(): void
    {
        if (app()->runningInConsole()) {
            App::setLocale(AppLocale::resolve(config('localization.console'))->value);
        }
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }

    protected function configureRateLimiting(): void
    {
        RateLimiter::for('login', function (Request $request) {
            $identity = Str::lower(trim((string) $request->input('email')));

            return Limit::perMinute(5)->by(Str::transliterate($identity).'|'.$request->ip());
        });

        RateLimiter::for('otp', function (Request $request) {
            $identity = (string) ($request->user()?->id ?? Str::lower(trim((string) $request->input('email'))));

            return Limit::perMinute(5)->by(Str::transliterate($identity).'|'.$request->ip());
        });

        RateLimiter::for('recovery', function (Request $request) {
            $identity = Str::lower(trim((string) $request->input('email', $request->user()?->email)));

            return Limit::perMinute(5)->by(Str::transliterate($identity).'|'.$request->ip());
        });

        // Registration is limited twice: once per source IP, so one host cannot
        // mass-create accounts, and once per normalised identifier, so one
        // address cannot be hammered. Both are configurable; the defaults are
        // deliberately conservative.
        RateLimiter::for('registration', function (Request $request) {
            $identity = Str::lower(trim((string) $request->input('email')));
            $perIp = (int) config('registration.rate_limits.per_ip', 10);
            $perIdentifier = (int) config('registration.rate_limits.per_identifier', 5);

            return [
                Limit::perMinute($perIp)->by('registration-ip:'.$request->ip()),
                Limit::perMinute($perIdentifier)->by('registration-identity:'.Str::transliterate($identity).'|'.$request->ip()),
            ];
        });

        // Resending the link for an expired enrolment token. The token is the
        // credential, so the per-token axis is keyed by a DIGEST of it: the
        // rate-limit store must never hold something that can be redeemed.
        RateLimiter::for('enrolment-resend', function (Request $request) {
            $token = (string) $request->route('token');
            $perIp = (int) config('registration.rate_limits.enrolment_resend_per_ip', 5);
            $perToken = (int) config('registration.rate_limits.enrolment_resend_per_token', 3);

            return [
                Limit::perMinute($perIp)->by('enrolment-resend-ip:'.$request->ip()),
                Limit::perMinute($perToken)->by('enrolment-resend-token:'.hash('sha256', $token)),
            ];
        });

        // The unauthenticated "I do not have a link" form sends mail, so it is
        // limited per source IP and per normalised address. The address axis is
        // NOT keyed by IP on purpose: a distributed set of hosts must not be
        // able to flood one mailbox.
        RateLimiter::for('enrolment-link', function (Request $request) {
            $identity = Str::lower(trim((string) $request->input('email')));
            $perIp = (int) config('registration.rate_limits.enrolment_link_per_ip', 5);
            $perIdentifier = (int) config('registration.rate_limits.enrolment_link_per_identifier', 3);

            return [
                Limit::perMinute($perIp)->by('enrolment-link-ip:'.$request->ip()),
                Limit::perMinute($perIdentifier)->by('enrolment-link-identity:'.Str::transliterate($identity)),
            ];
        });
    }

    protected function configureObservers(): void
    {
        Category::observe(CategoryObserver::class);
        Product::observe(ProductObserver::class);
        Store::observe(StoreObserver::class);
    }

    protected function configureHttpMacros(): void
    {
        Http::macro('safeUrl', function () {
            return Http::timeout(10)
                ->connectTimeout(5)
                ->withHeaders([
                    'User-Agent' => 'HomeRoot Recipe Importer (https://homelab)',
                    'Accept' => 'text/html,application/json,application/ld+json,*/*',
                ])
                ->withMiddleware(new SecureHttpMiddleware);
        });
    }

    protected function configureOpenApi(): void
    {
        Scramble::configure()->withOperationTransformers(function (Operation $operation, RouteInfo $routeInfo): void {
            $middleware = collect((array) $routeInfo->route->getAction('middleware'))
                ->first(fn (mixed $name): bool => is_string($name) && str_starts_with($name, 'idempotency'));

            if (! is_string($middleware)) {
                return;
            }

            $alreadyDocumented = collect($operation->parameters)->contains(
                fn (mixed $parameter): bool => $parameter instanceof OpenApiParameter
                    && $parameter->in === 'header'
                    && strcasecmp($parameter->name, 'Idempotency-Key') === 0,
            );

            if ($alreadyDocumented) {
                return;
            }

            $operation->addParameters([
                OpenApiParameter::make('Idempotency-Key', 'header')
                    ->required($middleware === 'idempotency')
                    ->description('Stable retry key. Responses are replayed for the same user and payload for 86400 seconds.')
                    ->setSchema(Schema::fromType((new StringType)->setMin(1)->setMax(255))),
            ]);
        });

        Scramble::afterOpenApiGenerated(function (OpenApi $openApi): void {
            $this->documentAiProposalVariants($openApi);
        });
    }

    private function documentAiProposalVariants(OpenApi $openApi): void
    {
        $components = $openApi->components;
        $resourceSchemaName = collect(array_keys($components->schemas))
            ->first(fn (string $name): bool => class_basename($name) === class_basename(AiProposalResource::class));

        if (! is_string($resourceSchemaName)) {
            return;
        }

        $resourceSchema = $components->getSchema($resourceSchemaName);

        if (! $resourceSchema->type instanceof ObjectType) {
            return;
        }

        $shoppingItemsProposal = $resourceSchema->type->clone()
            ->addProperty('type', (new StringType)->const('add_shopping_items'))
            ->addProperty('status', (new StringType)->enum(['pending', 'accepted', 'rejected', 'expired']))
            ->addProperty('payload', $this->shoppingItemsProposalPayload());
        $createRecipeProposal = $resourceSchema->type->clone()
            ->addProperty('type', (new StringType)->const('create_recipe'))
            ->addProperty('status', (new StringType)->enum(['pending', 'accepted', 'rejected', 'expired']))
            ->addProperty('payload', $this->createRecipeProposalPayload($openApi));

        $shoppingSchemaName = 'AssistantAddShoppingItemsProposal';
        $recipeSchemaName = 'AssistantCreateRecipeProposal';
        $shoppingReference = $components->addSchema($shoppingSchemaName, Schema::fromType($shoppingItemsProposal));
        $recipeReference = $components->addSchema($recipeSchemaName, Schema::fromType($createRecipeProposal));

        $resourceSchema->type = new DiscriminatedOneOfType(
            [$shoppingReference, $recipeReference],
            'type',
            [
                'add_shopping_items' => '#/components/schemas/'.$shoppingSchemaName,
                'create_recipe' => '#/components/schemas/'.$recipeSchemaName,
            ],
        );
    }

    private function shoppingItemsProposalPayload(): ObjectType
    {
        $item = (new ObjectType)
            ->addProperty('product_id', (new StringType)->format('uuid')->nullable(true))
            ->addProperty('custom_name', (new StringType)->nullable(true))
            ->addProperty('quantity', new NumberType)
            ->addProperty('unit', (new StringType)->nullable(true))
            ->addProperty('notes', (new StringType)->nullable(true));

        return (new ObjectType)
            ->addProperty('list_id', (new StringType)->format('uuid'))
            ->addProperty('items', (new ArrayType)->setItems($item)->setMin(1))
            ->setRequired(['list_id', 'items']);
    }

    private function createRecipeProposalPayload(OpenApi $openApi): ObjectType
    {
        $recipe = $openApi->components->hasSchema(StoreRecipeApiRequest::class)
            ? $openApi->components->getSchemaReference(StoreRecipeApiRequest::class)
            : new ObjectType;

        return (new ObjectType)
            ->addProperty('recipe', $recipe)
            ->setRequired(['recipe']);
    }
}
