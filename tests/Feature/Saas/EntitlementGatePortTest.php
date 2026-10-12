<?php

namespace Tests\Feature\Saas;

use App\Contracts\EntitlementGate;
use App\Exceptions\EntitlementQuotaExceededException;
use App\Models\User;
use App\Providers\AppServiceProvider;
use App\Support\NullEntitlementGate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Tests\TestCase;

class EntitlementGatePortTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
    }

    public function test_container_resolves_an_entitlement_gate_implementation(): void
    {
        $gate = app(EntitlementGate::class);

        // The concrete class depends on whether the optional commercial package is installed, so
        // this asserts the port is always resolvable without pinning a mode-specific implementation.
        $this->assertInstanceOf(EntitlementGate::class, $gate);
    }

    public function test_bind_if_does_not_override_an_existing_binding(): void
    {
        $double = new class implements EntitlementGate
        {
            public function assertFeature(User $user, string $feature): void {}

            public function reserveUsage(User $user, string $metric, int $amount, string $operationId): string
            {
                return "{$user->id}:{$operationId}";
            }

            public function commitUsage(string $operationId): void {}

            public function releaseUsage(string $operationId): void {}

            public function assertHouseholdOwnershipCapacity(User $user, int $incoming = 1): void {}

            public function reserveTicketRecognition(User $user, string $importId): string
            {
                return 'double:'.$importId;
            }
        };

        $this->app->bind(EntitlementGate::class, fn (): EntitlementGate => $double);

        $this->app->bindIf(EntitlementGate::class, NullEntitlementGate::class);

        $this->assertSame($double, $this->app->make(EntitlementGate::class));
    }

    public function test_null_gate_fallback_is_used_when_nothing_else_is_bound(): void
    {
        // `unset($container[$abstract])` is how this Laravel version drops a binding: the Container
        // implements ArrayAccess and its offsetUnset clears the binding, the resolved instance and
        // the resolved flag (Container::forgetInstance only clears cached instances).
        unset($this->app[EntitlementGate::class]);

        $this->assertFalse($this->app->bound(EntitlementGate::class));

        (new AppServiceProvider($this->app))->register();

        $this->assertInstanceOf(NullEntitlementGate::class, $this->app->make(EntitlementGate::class));
    }

    public function test_null_gate_feature_and_reservation_checks_are_no_ops(): void
    {
        $gate = new NullEntitlementGate;

        $gate->assertFeature($this->user, 'recipes');
        $gate->reserveUsage($this->user, 'ai_assistant_monthly', 1, 'op-1');
        $gate->commitUsage('op-1');
        $gate->releaseUsage('op-2');
        $gate->assertHouseholdOwnershipCapacity($this->user);
        $gate->assertHouseholdOwnershipCapacity($this->user, 5);

        $this->assertTrue(true);
    }

    public function test_null_gate_reserve_usage_returns_the_canonical_user_scoped_id(): void
    {
        $operationId = (new NullEntitlementGate)->reserveUsage($this->user, 'ai_assistant_monthly', 1, 'op-1');

        // Nothing is persisted in this mode, but the canonical shape must still
        // match the commercial adapter's so both modes agree on the handle.
        $this->assertSame($this->user->id.':op-1', $operationId);
    }

    public function test_null_gate_ticket_recognition_returns_a_usable_operation_id(): void
    {
        $operationId = (new NullEntitlementGate)->reserveTicketRecognition($this->user, 'import-42');

        $this->assertNotSame('', $operationId);
        $this->assertStringContainsString('import-42', $operationId);
    }

    public function test_quota_exception_renders_the_402_envelope_for_json_requests(): void
    {
        $exception = new EntitlementQuotaExceededException(
            (string) $this->user->id,
            'ai_assistant_monthly',
            cap: 50,
            used: 50,
            requested: 1,
        );

        $request = Request::create('/api/v1/recipes/ai-generate', 'POST', server: ['HTTP_ACCEPT' => 'application/json']);
        $response = $exception->render($request);

        $this->assertSame(402, $response->status());
        $payload = $response->getData(true);
        $this->assertSame('quota_exceeded', $payload['code']);
        $this->assertSame("You have reached your plan's quota: 50 of 50 already used for this billing period.", $payload['message']);
        $this->assertSame([], (array) $payload['errors']);
    }

    public function test_quota_exception_redirects_back_with_a_saas_flash_for_web_requests(): void
    {
        $exception = new EntitlementQuotaExceededException(
            (string) $this->user->id,
            'ai_assistant_monthly',
            cap: 50,
            used: 50,
            requested: 1,
        );

        $request = Request::create('/recipes/create', 'GET');
        $request->headers->set('referer', 'http://localhost/recipes/create');
        $request->setLaravelSession($this->app['session.store']);
        $this->app->instance('request', $request);

        $response = $exception->render($request);

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertSame('http://localhost/recipes/create', $response->getTargetUrl());
        $this->assertSame(
            "You have reached your plan's quota: 50 of 50 already used for this billing period.",
            $this->app['session.store']->get('saas'),
        );
    }
}
