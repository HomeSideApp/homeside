<?php

namespace Tests\Feature\Saas;

use App\Contracts\EntitlementGate;
use App\Models\User;
use App\Support\NullEntitlementGate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Covers the Community-owned 'entitlement:<key>' middleware that replaces the
 * commercial package's 'saas.feature:<key>' alias.
 *
 * The route is registered per test so the assertions exercise only this gate,
 * and the final test pins the invariant that no route in the app depends on
 * the package-registered alias any more.
 */
class EnsureEntitlementMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_passes_the_gate_with_the_null_gate(): void
    {
        $this->app->bind(EntitlementGate::class, NullEntitlementGate::class);
        $this->registerProtectedRoute('recipes');

        $this->actingAs(User::factory()->create())
            ->get('/_test/entitlement')
            ->assertOk()
            ->assertSee('ok');
    }

    public function test_a_denying_gate_returns_403_and_is_actually_evaluated(): void
    {
        $gate = $this->bindDenyingGate();
        $this->registerProtectedRoute('recipes');

        $this->actingAs(User::factory()->create())
            ->get('/_test/entitlement')
            ->assertStatus(403);

        $this->assertSame(['recipes'], $gate->evaluated);
    }

    public function test_unknown_feature_key_is_refused_when_the_registry_has_declarations(): void
    {
        Log::spy();
        $this->registerProtectedRoute('not_a_real_feature');

        $this->actingAs(User::factory()->create())
            ->get('/_test/entitlement')
            ->assertStatus(403);

        Log::shouldHaveReceived('warning')
            ->once()
            ->withArgs(fn (string $message, array $context): bool => ($context['feature'] ?? null) === 'not_a_real_feature');
    }

    public function test_unknown_feature_key_is_allowed_when_the_registry_is_empty(): void
    {
        config(['capabilities' => []]);
        $this->app->bind(EntitlementGate::class, NullEntitlementGate::class);
        $this->registerProtectedRoute('not_a_real_feature');

        $this->actingAs(User::factory()->create())
            ->get('/_test/entitlement')
            ->assertOk();
    }

    public function test_an_unauthenticated_request_is_owned_by_the_auth_layer(): void
    {
        $gate = $this->bindDenyingGate();
        $this->registerProtectedRoute('recipes');

        $this->get('/_test/entitlement')->assertRedirect('/login');

        // The gate must not run (and must not turn the guest into a 403).
        $this->assertSame([], $gate->evaluated);
    }

    public function test_no_route_uses_the_package_registered_saas_feature_alias(): void
    {
        $this->assertSame([], $this->offendingSaasFeatureRoutes(), 'No route may depend on the package-registered saas.feature alias.');
    }

    public function test_the_structural_check_detects_a_legacy_saas_feature_route(): void
    {
        // Proves the invariant above is not vacuous: a route still behind the
        // package alias must make the scan report an offender.
        Route::middleware('saas.feature:recipes')->get('/_test/legacy-gate', fn (): string => 'ok');

        $this->assertSame(['/_test/legacy-gate'], $this->offendingSaasFeatureRoutes());
    }

    private function registerProtectedRoute(string $feature): void
    {
        Route::middleware(['auth', 'entitlement:'.$feature])
            ->get('/_test/entitlement', fn (): string => 'ok');
    }

    /**
     * @return list<string>
     */
    private function offendingSaasFeatureRoutes(): array
    {
        $offenders = [];

        foreach (Route::getRoutes() as $route) {
            foreach ($route->gatherMiddleware() as $middleware) {
                if (is_string($middleware) && str_starts_with($middleware, 'saas.feature')) {
                    $offenders[] = '/'.$route->uri();
                }
            }
        }

        return $offenders;
    }

    /**
     * Bind a gate that records the features it was asked to evaluate and always
     * denies, so the test can prove the middleware delegated to the port.
     *
     * @return object{evaluated: list<string>}
     */
    private function bindDenyingGate(): object
    {
        $gate = new class implements EntitlementGate
        {
            /** @var list<string> */
            public array $evaluated = [];

            public function assertFeature(User $user, string $feature): void
            {
                $this->evaluated[] = $feature;

                abort(403, __('app.errors.permission_denied'));
            }

            public function reserveUsage(User $user, string $metric, int $amount, string $operationId): string
            {
                return "{$user->id}:{$operationId}";
            }

            public function commitUsage(string $operationId): void {}

            public function releaseUsage(string $operationId): void {}

            public function assertHouseholdOwnershipCapacity(User $user, int $incoming = 1): void {}

            public function reserveTicketRecognition(User $user, string $importId): string
            {
                return 'denied:'.$importId;
            }
        };

        $this->app->bind(EntitlementGate::class, fn (): EntitlementGate => $gate);

        return $gate;
    }
}
