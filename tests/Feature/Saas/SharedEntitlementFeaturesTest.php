<?php

namespace Tests\Feature\Saas;

use App\Contracts\EntitlementGate;
use App\Models\User;
use App\Support\NullEntitlementGate;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Covers the shared `entitlement.features` Inertia prop: the plan gate the
 * sidebar filters on.
 *
 * The prop has to carry three distinct answers without ever leaking plan
 * internals: `null` when no entitlement system applies (Community / OSS, so the
 * sidebar must keep showing every item as today), the effective map when a plan
 * gate is bound, and `null` again for guests.
 */
class SharedEntitlementFeaturesTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_null_gate_reports_no_feature_map(): void
    {
        $user = User::factory()->create();

        $this->assertNull(
            (new NullEntitlementGate)->features($user),
            'A self-hosted install has no plan, so the port must answer null rather than an empty map.',
        );
    }

    public function test_community_binding_shares_a_null_feature_map(): void
    {
        $this->app->bind(EntitlementGate::class, NullEntitlementGate::class);

        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('profile.edit'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('entitlement.features', null)
            );
    }

    public function test_guests_share_a_null_feature_map_without_consulting_the_gate(): void
    {
        $gate = $this->bindFeatureGate(['recipes' => true]);

        $this->get(route('login'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('entitlement.features', null)
            );

        $this->assertSame([], $gate->featuresCalls, 'Guests own no plan, so the port must not be asked.');
    }

    public function test_a_bound_gate_shares_its_effective_feature_map(): void
    {
        $featureMap = [
            'lists' => true,
            'recipes' => false,
            'economy' => true,
            'ai_assistant' => false,
            'ai_image_recognition' => false,
        ];

        $this->bindFeatureGate($featureMap);

        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('profile.edit'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('entitlement.features', $featureMap)
            );
    }

    /**
     * The bug this prop exists for: the `user` role holds the recipes permission,
     * so a permission-only sidebar advertises a module a `free` plan denies.
     * The shared prop must therefore carry the denial next to that permission.
     */
    public function test_the_shared_map_reports_a_denied_module_the_user_still_has_permission_for(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->bindFeatureGate(['lists' => true, 'recipes' => false]);

        $user = User::factory()->create();
        $user->assignRole('user');

        $response = $this->actingAs($user)->get(route('dashboard'))->assertOk();

        $response->assertInertia(fn (Assert $page): Assert => $page
            ->where('auth.permissions', fn ($permissions): bool => collect($permissions)->contains('households.recipes.index'))
            ->where('entitlement.features.recipes', false)
            ->where('entitlement.features.lists', true)
        );
    }

    /**
     * Bind a recording gate with a fixed effective feature map.
     *
     * @param  array<string, bool>  $featureMap  Feature key => enabled.
     * @return object{featureMap: array<string, bool>, featuresCalls: list<string>}
     */
    private function bindFeatureGate(array $featureMap): object
    {
        $gate = new class implements EntitlementGate
        {
            /** @var array<string, bool> */
            public array $featureMap = [];

            /** @var list<string> */
            public array $featuresCalls = [];

            /** @return array<string, bool>|null */
            public function features(User $user): ?array
            {
                $this->featuresCalls[] = $user->id;

                return $this->featureMap;
            }

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

        $gate->featureMap = $featureMap;

        $this->app->instance(EntitlementGate::class, $gate);

        return $gate;
    }
}
