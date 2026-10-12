<?php

namespace Tests\Feature\Saas;

use App\Actions\Households\CreateHousehold;
use App\Contracts\EntitlementGate;
use App\Models\User;
use App\Providers\AppServiceProvider;
use App\Support\NullEntitlementGate;
use HomeSide\Saas\Actions\AssignGrant;
use HomeSide\Saas\Database\Seeders\SaasPlanSeeder;
use HomeSide\Saas\Models\Grant;
use HomeSide\Saas\Models\Plan;
use HomeSide\Saas\Models\PlanVersion;
use HomeSide\Saas\SaasServiceProvider;
use HomeSide\Saas\Services\SaasEntitlementGate;
use HomeSide\Saas\Support\UsageMeter;
use Illuminate\Config\Repository;
use Illuminate\Container\Container;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionClass;
use ReflectionNamedType;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Tests\TestCase;

/**
 * Proves the Community `bindIf` plus package `bind` design is independent of
 * the order in which the two providers register, and that no lifecycle stage
 * pins a stale fallback.
 *
 * The order and rebinding scenarios drive the real `register()` methods of the
 * real providers against a bare container, so the property under test (a plain
 * bind versus a `bindIf`) is exercised exactly as Laravel exercises it without
 * relying on the booted application's already-finished registration. The
 * scenarios that need real package services (the metered ledger and the package
 * grant action) use the booted application and are skipped when the commercial
 * package is not installed.
 *
 * @see AppServiceProvider::configureEntitlements()
 * @see SaasServiceProvider::register()
 */
class EntitlementBindingLifecycleTest extends TestCase
{
    use RefreshDatabase;

    /**
     * The two registration orders under test. In a real install Laravel
     * splices package-discovered providers before the app's own providers, but
     * the design must hold for either order.
     *
     * @return array<string, array{0: list<class-string>}>
     */
    public static function providerOrders(): array
    {
        return [
            'Community first, package second' => [[AppServiceProvider::class, SaasServiceProvider::class]],
            'package first, Community second' => [[SaasServiceProvider::class, AppServiceProvider::class]],
        ];
    }

    #[DataProvider('providerOrders')]
    public function test_registration_order_always_ends_on_the_package_implementation(array $providers): void
    {
        $this->requireSaasPackage();

        $container = $this->registerProvidersInOrder($providers);

        $this->assertInstanceOf(SaasEntitlementGate::class, $container->make(EntitlementGate::class));
    }

    public function test_package_absent_always_resolves_the_null_gate(): void
    {
        // With the package absent the two "orders" collapse to this single
        // Community registration, so one assertion covers the absent mode.
        $container = $this->registerProvidersInOrder([AppServiceProvider::class]);

        $this->assertInstanceOf(NullEntitlementGate::class, $container->make(EntitlementGate::class));
    }

    public function test_a_previously_resolved_fallback_is_not_cached_before_the_package_binds(): void
    {
        $this->requireSaasPackage();

        $container = $this->freshContainer();

        (new AppServiceProvider($container))->register();

        // Resolve the Community fallback eagerly, the way an eager consumer
        // would, before the package has had a chance to bind.
        $this->assertInstanceOf(NullEntitlementGate::class, $container->make(EntitlementGate::class));
        $this->assertFalse($container->isShared(EntitlementGate::class));

        (new SaasServiceProvider($container))->register();

        // The earlier resolution must not survive as a cached singleton.
        $this->assertInstanceOf(SaasEntitlementGate::class, $container->make(EntitlementGate::class));
        $this->assertFalse($container->isShared(EntitlementGate::class));
    }

    public function test_the_contract_binding_is_not_shared_and_stays_replaceable(): void
    {
        $this->requireSaasPackage();

        $container = $this->registerProvidersInOrder([AppServiceProvider::class, SaasServiceProvider::class]);

        $this->assertFalse($container->isShared(EntitlementGate::class));

        $first = $container->make(EntitlementGate::class);
        $second = $container->make(EntitlementGate::class);

        $this->assertInstanceOf(SaasEntitlementGate::class, $first);
        $this->assertNotSame($first, $second, 'A plain bind must build a new instance per resolution, never replay a cached singleton.');

        $double = $this->entitlementDouble();

        $container->bind(EntitlementGate::class, fn (): EntitlementGate => $double);

        $this->assertSame($double, $container->make(EntitlementGate::class));
        $this->assertSame($double, $container->make(EntitlementGate::class));

        $container->bind(EntitlementGate::class, SaasEntitlementGate::class);

        $this->assertInstanceOf(SaasEntitlementGate::class, $container->make(EntitlementGate::class));
    }

    public function test_a_freshly_resolved_consumer_uses_the_current_binding(): void
    {
        $this->requireSaasPackage();

        $container = $this->registerProvidersInOrder([AppServiceProvider::class, SaasServiceProvider::class]);

        $before = $container->make(CreateHousehold::class);

        $this->assertInstanceOf(SaasEntitlementGate::class, $this->injectedPort($before));

        $double = $this->entitlementDouble();
        $container->bind(EntitlementGate::class, fn (): EntitlementGate => $double);

        $after = $container->make(CreateHousehold::class);

        // The failure mode this guards against: a consumer built before the
        // final bind would keep the fallback forever. A freshly resolved
        // consumer must receive the new binding.
        $this->assertNotSame($before, $after);
        $this->assertSame($double, $this->injectedPort($after));
    }

    public function test_a_long_lived_process_follows_the_current_context_not_the_first_resolution(): void
    {
        $this->requireSaasPackage();

        $this->seed(SaasPlanSeeder::class);
        config(['saas.enabled' => true]);

        $pro = $this->userWithPlan('pro');
        $free = User::factory()->create();

        $port = app(EntitlementGate::class);
        $this->assertInstanceOf(SaasEntitlementGate::class, $port);

        // One resolved port instance, two different users: the decision follows
        // the user passed to the call, so there is no first-user-wins memo on
        // the gate itself.
        $port->assertFeature($pro, 'recipes');
        $this->assertFeatureDenied(fn () => $port->assertFeature($free, 'recipes'));

        // Limitation: this app installs no persistent-worker runtime (no
        // Octane), so a real worker iteration cannot be simulated here. What
        // is genuinely observable in-process is asserted instead: the port
        // carries no decision state between calls, and the package's real
        // grant action flushes the resolver memo that would otherwise serve a
        // stale plan for the rest of a long-lived process.
        //
        // A plan change inside the same process, driven through the package's
        // real grant action (which flushes the per-user entitlement memo). The
        // already-resolved port and a freshly resolved one must both observe
        // the new context instead of replaying the first resolution.
        app(AssignGrant::class)->execute($pro, $this->planVersion('free'), null, 'entitlement binding lifecycle test');

        $this->assertFeatureDenied(fn () => $port->assertFeature($pro, 'recipes'));
        $this->assertFeatureDenied(fn () => app(EntitlementGate::class)->assertFeature($pro, 'recipes'));
    }

    public function test_reserving_the_same_operation_id_twice_does_not_double_count(): void
    {
        $this->requireSaasPackage();

        $this->seed(SaasPlanSeeder::class);
        config(['saas.enabled' => true]);

        $user = $this->userWithPlan('pro');
        $operationId = 'entitlement-binding-lifecycle-op';

        $port = app(EntitlementGate::class);
        $this->assertInstanceOf(SaasEntitlementGate::class, $port);

        // The real FeatureGate -> UsageMeter path with the kill switch on. The
        // replays carry the same caller id, so the port returns the same
        // canonical id both times and the two reservations collapse to one row.
        $committedId = $port->reserveUsage($user, 'ai_assistant_monthly', 3, $operationId);
        $this->assertSame($committedId, $port->reserveUsage($user, 'ai_assistant_monthly', 3, $operationId));

        $this->assertSame(3, app(UsageMeter::class)->used($user, 'ai_assistant_monthly'));
        $this->assertDatabaseCount('saas_usage_entries', 1);

        // Committing the replayed reservation must not add a second charge. It
        // takes the canonical id the port returned, not the bare caller id.
        $port->commitUsage($committedId);

        $this->assertSame(3, app(UsageMeter::class)->used($user, 'ai_assistant_monthly'));
        $this->assertDatabaseCount('saas_usage_entries', 1);
    }

    /**
     * Build a bare container carrying the same configuration the booted app
     * has, so the providers' `register()` methods behave identically.
     */
    private function freshContainer(): Container
    {
        $container = new Container;

        $container->instance('app', $container);
        $container->instance('config', new Repository(app('config')->all()));

        return $container;
    }

    /**
     * @param  list<class-string>  $providers
     */
    private function registerProvidersInOrder(array $providers): Container
    {
        $container = $this->freshContainer();

        foreach ($providers as $provider) {
            (new $provider($container))->register();
        }

        return $container;
    }

    /**
     * Skip the scenarios that need the commercial package when it is absent,
     * so a Community-only install still has a green suite.
     */
    private function requireSaasPackage(): void
    {
        if (! class_exists(SaasServiceProvider::class)) {
            $this->markTestSkipped('The homeside/saas package is not installed.');
        }
    }

    private function userWithPlan(string $planKey): User
    {
        $user = User::factory()->create();

        Grant::create([
            'user_id' => $user->id,
            'plan_version_id' => $this->planVersion($planKey)->id,
            'starts_at' => now()->subDay(),
            'reason' => 'entitlement binding lifecycle test',
        ]);

        return $user;
    }

    private function planVersion(string $planKey, int $version = 1): PlanVersion
    {
        /** @var PlanVersion $planVersion */
        $planVersion = Plan::query()
            ->where('key', $planKey)
            ->firstOrFail()
            ->versions()
            ->where('version', $version)
            ->whereNotNull('published_at')
            ->firstOrFail();

        return $planVersion;
    }

    private function assertFeatureDenied(callable $call): void
    {
        try {
            $call();
        } catch (HttpExceptionInterface $exception) {
            $this->assertSame(403, $exception->getStatusCode());

            return;
        }

        $this->fail('Expected the resolved port to deny the feature.');
    }

    /**
     * A port double that identifies itself so tests can prove which binding a
     * consumer received.
     */
    private function entitlementDouble(): EntitlementGate
    {
        return new class implements EntitlementGate
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
    }

    /**
     * Read the EntitlementGate promoted into the consumer's constructor.
     */
    private function injectedPort(object $consumer): ?EntitlementGate
    {
        $reflection = new ReflectionClass($consumer);
        $constructor = $reflection->getConstructor();

        if ($constructor === null) {
            return null;
        }

        foreach ($constructor->getParameters() as $parameter) {
            $type = $parameter->getType();

            if (! $type instanceof ReflectionNamedType || $type->getName() !== EntitlementGate::class) {
                continue;
            }

            if (! $parameter->isPromoted()) {
                return null;
            }

            $value = $reflection->getProperty($parameter->getName())->getValue($consumer);

            return $value instanceof EntitlementGate ? $value : null;
        }

        return null;
    }
}
