<?php

namespace Tests\Feature\Registration;

use App\Contracts\EntitlementGate;
use App\Exceptions\EntitlementQuotaExceededException;
use App\Http\Requests\StoreHouseholdRequest;
use App\Http\Resources\Users\UserResource;
use App\Models\User;
use App\Support\HouseholdAccess;
use App\Support\PlatformAccess;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Covers the nullable `can_create_households_override` tri-state.
 *
 * Semantics under test:
 *  - `null` delegates to Policy + EntitlementGate and is NOT permission;
 *  - `false` is an explicit prohibition;
 *  - there is no `true` state, and the removed permissive default never grants.
 */
class HouseholdCreationOverrideTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_factory_default_is_the_delegated_null_state(): void
    {
        $user = User::factory()->create();

        $this->assertNull($user->can_create_households_override);
        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'can_create_households_override' => null,
        ]);
    }

    public function test_the_delegated_null_state_allows_creation_on_a_self_hosted_install(): void
    {
        $user = User::factory()->create();

        $this->assertTrue(app(PlatformAccess::class)->canCreateHouseholds($user));
    }

    public function test_a_null_override_is_not_permission_when_the_entitlement_denies(): void
    {
        $user = User::factory()->create();
        $this->assertNull($user->can_create_households_override);

        $this->bindDenyingEntitlement();

        // The tri-state null is not permission: it only means "not
        // structurally forbidden". Capacity still decides, so the combined
        // presentation read is false even though the structural read passes.
        $this->assertTrue(app(PlatformAccess::class)->canCreateHouseholds($user));
        $this->assertFalse(app(PlatformAccess::class)->hasHouseholdOwnershipCapacity($user));
        $this->assertFalse(app(PlatformAccess::class)->mayCreateHouseholdNow($user));

        // The structural surfaces must NOT preempt the quota refusal: turning
        // this into a 403 is exactly the regression being fixed. The request is
        // authorized structurally and the 402 comes from the action transaction.
        $request = StoreHouseholdRequest::create('/households', 'POST');
        $request->setUserResolver(fn () => $user);
        $this->assertTrue($request->authorize());

        $routeRequest = Request::create('/api/v1/households', 'POST');
        $routeRequest->setUserResolver(fn () => $user);
        $route = new Route(['POST'], 'households', fn () => null);
        $route->name('api.v1.households.store');
        $routeRequest->setRouteResolver(fn () => $route);
        $this->assertTrue(app(HouseholdAccess::class)->routeDecision($routeRequest, 'households.store'));
    }

    public function test_a_null_override_with_an_entitlement_denial_answers_the_quota_outcome(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $user = User::factory()->create();
        $user->assignRole('user');
        $this->bindDenyingEntitlement();

        Sanctum::actingAs($user);

        $this->postJson(route('api.v1.households.store'), ['name' => 'Over the cap'])
            ->assertStatus(402)
            ->assertJson(['code' => 'quota_exceeded']);

        $this->assertDatabaseMissing('households', ['name' => 'Over the cap']);
    }

    public function test_a_structural_denial_answers_403_even_when_the_entitlement_allows(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $user = User::factory()->withoutHouseholdCreation()->create();
        $user->assignRole('user');

        Sanctum::actingAs($user);

        $this->postJson(route('api.v1.households.store'), ['name' => 'Structurally denied'])
            ->assertStatus(403);

        $this->assertDatabaseMissing('households', ['name' => 'Structurally denied']);
    }

    public function test_the_serialized_can_create_households_prop_is_false_at_the_capacity_limit(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $user = User::factory()->create();
        $user->assignRole('user');
        $this->bindDenyingEntitlement();

        // The UI read is the COMBINED answer, so the create control is hidden
        // for a user at their cap even though they are structurally allowed.
        $payload = (new UserResource($user))->resolve();

        $this->assertTrue($payload['households_enabled']);
        $this->assertNull($payload['can_create_households_override']);
        $this->assertFalse($payload['can_create_households']);

        // Hiding the control never changes the authoritative answer: a forced
        // request still receives the quota outcome (402), not a structural 403.
        Sanctum::actingAs($user);

        $this->postJson(route('api.v1.households.store'), ['name' => 'Forced at cap'])
            ->assertStatus(402)
            ->assertJson(['code' => 'quota_exceeded']);

        $this->assertDatabaseMissing('households', ['name' => 'Forced at cap']);
    }

    public function test_an_explicit_false_override_denies_even_when_the_entitlement_allows(): void
    {
        $user = User::factory()->withoutHouseholdCreation()->create();

        $this->assertFalse($user->can_create_households_override);
        $this->assertFalse(app(PlatformAccess::class)->canCreateHouseholds($user));

        $request = StoreHouseholdRequest::create('/households', 'POST');
        $request->setUserResolver(fn () => $user);
        $this->assertFalse($request->authorize());
    }

    public function test_disabled_households_deny_creation(): void
    {
        $user = User::factory()->create(['households_enabled' => false]);

        $this->assertFalse(app(PlatformAccess::class)->canCreateHouseholds($user));
    }

    public function test_a_suspended_user_cannot_create_households(): void
    {
        $user = User::factory()->suspended()->create();

        $this->assertFalse(app(PlatformAccess::class)->canCreateHouseholds($user));
    }

    public function test_the_admin_api_exposes_the_tri_state_and_rejects_true(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $target = User::factory()->create();

        $this->actingAs($admin)
            ->patchJson(route('api.v1.admin.users.update', $target), ['can_create_households_override' => true])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('can_create_households_override');

        $this->actingAs($admin)
            ->patchJson(route('api.v1.admin.users.update', $target), ['can_create_households_override' => false])
            ->assertOk();

        $this->assertFalse($target->refresh()->can_create_households_override);

        $this->actingAs($admin)
            ->patchJson(route('api.v1.admin.users.update', $target), ['can_create_households_override' => null])
            ->assertOk();

        $this->assertNull($target->refresh()->can_create_households_override);
    }

    /**
     * Bind a gate whose ownership-capacity assertion always fails.
     */
    private function bindDenyingEntitlement(): void
    {
        $this->app->instance(EntitlementGate::class, new class implements EntitlementGate
        {
            public function assertFeature(User $user, string $feature): void {}

            public function reserveUsage(User $user, string $metric, int $amount, string $operationId): string
            {
                return 'unused';
            }

            public function commitUsage(string $operationId): void {}

            public function releaseUsage(string $operationId): void {}

            public function assertHouseholdOwnershipCapacity(User $user, int $incoming = 1): void
            {
                throw new EntitlementQuotaExceededException($user->id, 'households', 0, 0, $incoming);
            }

            public function reserveTicketRecognition(User $user, string $importId): string
            {
                return 'unused';
            }
        });
    }
}
