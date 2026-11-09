<?php

namespace Tests\Feature\Saas;

use App\Actions\Economy\CreateEconomicImport;
use App\Actions\Economy\ReprocessEconomicImport;
use App\Actions\Economy\RetryEconomicImport;
use App\Actions\Households\CreateHousehold;
use App\Actions\Households\TransferHouseholdOwnership;
use App\Contracts\EntitlementGate;
use App\Data\Households\CreateHouseholdData;
use App\Http\Controllers\Api\V1\RecipeAiController as ApiRecipeAiController;
use App\Http\Controllers\Web\RecipeAiController as WebRecipeAiController;
use App\Models\User;
use HomeSide\AiAgents\AiAgentManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionClass;
use ReflectionNamedType;
use RuntimeException;
use Tests\TestCase;

/**
 * Proves the Community consumers are wired to the app-owned EntitlementGate
 * port by injection instead of reaching into the commercial package.
 *
 * The commercial package is a legitimate dev dependency in this repository, so
 * the decoupling invariant is enforced two ways: the consumers resolve with the
 * port injected, and a filesystem scan fails the suite if any application file
 * starts importing HomeSide\Saas again.
 *
 * The scan covers `app/` AND `database/`: the E2E tooling is not production
 * code, but it is still application code, and scanning only `app/` is exactly
 * how package imports survived in the seeders.
 */
class ConsumerEntitlementInjectionTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Every consumer migrated onto the port by this work unit.
     *
     * @return array<string, array{0: class-string}>
     */
    public static function consumers(): array
    {
        return [
            'CreateHousehold' => [CreateHousehold::class],
            'TransferHouseholdOwnership' => [TransferHouseholdOwnership::class],
            'CreateEconomicImport' => [CreateEconomicImport::class],
            'ReprocessEconomicImport' => [ReprocessEconomicImport::class],
            'RetryEconomicImport' => [RetryEconomicImport::class],
            'Web RecipeAiController' => [WebRecipeAiController::class],
            'Api RecipeAiController' => [ApiRecipeAiController::class],
        ];
    }

    #[DataProvider('consumers')]
    public function test_consumer_resolves_from_the_container_with_the_port_injected(string $consumer): void
    {
        $instance = $this->app->make($consumer);

        $this->assertInstanceOf(
            EntitlementGate::class,
            $this->injectedPort($instance),
            "{$consumer} must receive App\\Contracts\\EntitlementGate by injection.",
        );
    }

    public function test_representative_action_calls_the_port(): void
    {
        $user = User::factory()->create();
        $spy = $this->bindRecordingGate();

        $this->app->make(CreateHousehold::class)->execute(
            CreateHouseholdData::fromArray(['name' => 'Casa de prueba']),
            $user,
        );

        $this->assertContains('assertHouseholdOwnershipCapacity:1', $spy->calls);
    }

    public function test_representative_controller_flow_meters_through_the_port(): void
    {
        $user = User::factory()->create();
        $spy = $this->bindRecordingGate();

        // The action is final, so drive the metering path through its real
        // dependency by making the AI manager fail: the controller then has to
        // reserve and release the credit through the injected port.
        $this->mock(AiAgentManager::class, function ($mock): void {
            $mock->shouldReceive('run')->andThrow(new RuntimeException('provider unavailable'));
        });

        $this->actingAs($user)
            ->postJson(route('recipes.ai-generate'), ['prompt' => 'Una tortilla'])
            ->assertStatus(422);

        // Two-phase gating by design. The route carries Community's own
        // `entitlement:recipes` gate, which evaluates the app-owned port, and
        // the controller then checks `ai_assistant` through the same port — so
        // a successful request reaches the port twice for feature checks.
        // Before this work unit the route carried the commercial package's
        // `saas.feature:recipes` middleware, which bypassed the port entirely,
        // so only the controller's check appeared here. The product behaviour
        // is unchanged: the same two feature checks still happen, now both
        // through the Community-owned port.
        $this->assertSame('assertFeature:recipes', $spy->calls[0] ?? null, 'Route-level entitlement:recipes gate.');
        $this->assertSame('assertFeature:ai_assistant', $spy->calls[1] ?? null, 'Controller-level ai_assistant check.');
        $this->assertSame('reserveUsage:ai_assistant_monthly', $spy->calls[2] ?? null, 'Metered reservation.');
        $this->assertStringStartsWith('releaseUsage:'.$user->id.':recipeai:', $spy->calls[3] ?? '', 'Released the canonical id the port returned, never the raw caller id.');
        $this->assertSame('releaseUsage:'.$spy->lastReservationId, $spy->calls[3] ?? null);
        $this->assertCount(4, $spy->calls);
    }

    public function test_no_app_file_imports_the_commercial_saas_package(): void
    {
        $offenders = [];

        foreach ([app_path(), database_path()] as $directory) {
            foreach (File::allFiles($directory) as $file) {
                if ($file->getExtension() !== 'php') {
                    continue;
                }

                if (str_contains($file->getContents(), 'HomeSide\Saas')) {
                    $offenders[] = str_replace(base_path().DIRECTORY_SEPARATOR, '', $file->getPathname());
                }
            }
        }

        $this->assertSame([], $offenders, 'Community code must depend on its own port, never HomeSide\Saas.');
    }

    /**
     * Bind a recording double over the port so the observer can assert which
     * port method each consumer invoked, and with which vocabulary.
     *
     * @return object{calls: list<string>, lastReservationId: ?string}
     */
    private function bindRecordingGate(): object
    {
        $spy = new class implements EntitlementGate
        {
            /** @var list<string> */
            public array $calls = [];

            /** Canonical id returned by the most recent reservation, if any. */
            public ?string $lastReservationId = null;

            public function assertFeature(User $user, string $feature): void
            {
                $this->calls[] = "assertFeature:{$feature}";
            }

            public function reserveUsage(User $user, string $metric, int $amount, string $operationId): string
            {
                $this->calls[] = "reserveUsage:{$metric}";

                // Mirror the null gate / commercial adapter shape: the canonical
                // id is user-scoped, so it differs from the raw caller id.
                return $this->lastReservationId = "{$user->id}:{$operationId}";
            }

            public function commitUsage(string $operationId): void
            {
                $this->calls[] = "commitUsage:{$operationId}";
            }

            public function releaseUsage(string $operationId): void
            {
                $this->calls[] = "releaseUsage:{$operationId}";
            }

            public function assertHouseholdOwnershipCapacity(User $user, int $incoming = 1): void
            {
                $this->calls[] = "assertHouseholdOwnershipCapacity:{$incoming}";
            }

            public function reserveTicketRecognition(User $user, string $importId): string
            {
                $this->calls[] = "reserveTicketRecognition:{$importId}";

                return "unmetered:{$importId}";
            }
        };

        $this->app->bind(EntitlementGate::class, fn (): EntitlementGate => $spy);

        return $spy;
    }

    /**
     * Read the EntitlementGate promoted into the consumer's constructor.
     *
     * @return EntitlementGate|null The injected port, or null when the consumer does not take one.
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
