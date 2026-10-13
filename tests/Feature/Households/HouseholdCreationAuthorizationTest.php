<?php

namespace Tests\Feature\Households;

use App\Actions\Households\CreateHousehold;
use App\Actions\Households\TransferHouseholdOwnership;
use App\Contracts\EntitlementGate;
use App\Data\Households\CreateHouseholdData;
use App\Enums\HouseholdRole;
use App\Exceptions\EntitlementQuotaExceededException;
use App\Http\Requests\StoreHouseholdRequest;
use App\Models\Household;
use App\Models\HouseholdMember;
use App\Models\User;
use App\Support\PlatformAccess;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Tests\Feature\Households\Support\RecordingEntitlementGate;
use Tests\TestCase;

/**
 * Proves the two creation layers are independent and correctly ordered:
 * HouseholdPolicy is the structural gate and the EntitlementGate is the
 * capacity gate, asserted INSIDE the creation transaction.
 *
 * These scenarios drive a recording double over the app-owned port instead of
 * the commercial adapter, so they run on every install. They prove the
 * DELEGATION CONTRACT (who is asked, in what order, inside or outside the
 * transaction), not production entitlement values; the real-gate behaviour is
 * proven in HouseholdOwnershipCapacityTest and HouseholdCreationConcurrencyTest.
 */
class HouseholdCreationAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_create_policy_delegates_the_structural_decision_to_platform_access(): void
    {
        $allowed = User::factory()->create();
        $forbidden = User::factory()->withoutHouseholdCreation()->create();
        $suspended = User::factory()->suspended()->create();
        $disabled = User::factory()->create(['households_enabled' => false]);

        $this->assertTrue(Gate::forUser($allowed)->allows('create', Household::class));
        $this->assertFalse(Gate::forUser($forbidden)->allows('create', Household::class));
        $this->assertFalse(Gate::forUser($suspended)->allows('create', Household::class));
        $this->assertFalse(Gate::forUser($disabled)->allows('create', Household::class));

        $this->assertTrue($allowed->can('create', Household::class));
        $this->assertFalse($forbidden->can('create', Household::class));
        $this->assertFalse($suspended->can('create', Household::class));
        $this->assertFalse($disabled->can('create', Household::class));
    }

    public function test_the_store_request_authorizes_through_the_create_policy(): void
    {
        $forbidden = User::factory()->withoutHouseholdCreation()->create();
        $allowed = User::factory()->create();

        $refusingRequest = StoreHouseholdRequest::create('/households', 'POST');
        $refusingRequest->setUserResolver(fn () => $forbidden);
        $this->assertFalse($refusingRequest->authorize());

        $allowingRequest = StoreHouseholdRequest::create('/households', 'POST');
        $allowingRequest->setUserResolver(fn () => $allowed);
        $this->assertTrue($allowingRequest->authorize());
    }

    public function test_a_structural_denial_refuses_creation_without_opening_a_transaction(): void
    {
        $user = User::factory()->withoutHouseholdCreation()->create();
        $gate = new RecordingEntitlementGate;
        $this->app->instance(EntitlementGate::class, $gate);

        try {
            app(CreateHousehold::class)->execute($this->householdData('Structurally blocked'), $user);
            $this->fail('A structural denial must refuse household creation.');
        } catch (AuthorizationException) {
            // Expected: the policy is the structural gate.
        }

        $this->assertSame(
            0,
            $gate->capacityCallCount(),
            'The policy is structural; a structural refusal must not borrow the capacity gate.',
        );
        $this->assertDatabaseMissing('households', ['name' => 'Structurally blocked']);
        $this->assertSame(0, Household::query()->where('owner_id', $user->id)->count());
    }

    public function test_the_capacity_assertion_runs_inside_the_creation_transaction(): void
    {
        $user = User::factory()->create();
        $gate = new RecordingEntitlementGate;
        $this->app->instance(EntitlementGate::class, $gate);

        $base = DB::transactionLevel();

        app(CreateHousehold::class)->execute($this->householdData('Two layers'), $user);

        $this->assertSame(
            [$base, $base + 1],
            $gate->capacityLevels,
            'The policy read runs before the transaction and the authoritative capacity check inside it.',
        );
        $this->assertSame([1, 1], $gate->incomingAmounts, 'Both layers ask for one incoming household.');
        $this->assertDatabaseHas('households', ['name' => 'Two layers', 'owner_id' => $user->id]);
    }

    public function test_a_capacity_denial_inside_the_transaction_rolls_back_the_whole_creation(): void
    {
        $user = User::factory()->create();
        $gate = new RecordingEntitlementGate;
        $gate->denyAfterCapacityCalls = 1; // The pre-check passes; the authoritative check refuses.
        $this->app->instance(EntitlementGate::class, $gate);

        try {
            app(CreateHousehold::class)->execute($this->householdData('Rolled back'), $user);
            $this->fail('A capacity denial inside the transaction must refuse household creation.');
        } catch (EntitlementQuotaExceededException $exception) {
            $this->assertSame('households_own', $exception->metric);
            $this->assertSame(1, $exception->requested);
        }

        $this->assertSame(2, $gate->capacityCallCount());
        $this->assertDatabaseMissing('households', ['name' => 'Rolled back']);
        $this->assertSame(0, Household::query()->where('owner_id', $user->id)->count());
        $this->assertSame(0, $user->householdMemberships()->count());
        $this->assertNull($user->refresh()->active_household_id);
    }

    public function test_the_creation_entitlement_read_is_memoized_per_request_but_structure_is_re_read(): void
    {
        $user = User::factory()->create();
        $gate = new RecordingEntitlementGate;
        $this->app->instance(EntitlementGate::class, $gate);

        $this->assertTrue(app(PlatformAccess::class)->canCreateHouseholds($user));
        $this->assertTrue(app(PlatformAccess::class)->canCreateHouseholds($user));
        $this->assertTrue(app(PlatformAccess::class)->canCreateHouseholds($user));

        $this->assertSame(1, $gate->capacityCallCount(), 'The entitlement read is memoized per request.');

        // The structural layers are deliberately NOT memoized: an administrative
        // change inside the same request is still observed, and it must not cost
        // a second entitlement read.
        $user->update(['can_create_households_override' => false]);

        $this->assertFalse(app(PlatformAccess::class)->canCreateHouseholds($user));
        $this->assertSame(1, $gate->capacityCallCount());
    }

    public function test_transferring_ownership_asserts_the_receivers_capacity_inside_the_transaction(): void
    {
        [$owner, $household, $receiver] = $this->householdWithOwnerAndAdmin();
        $gate = new RecordingEntitlementGate;
        $this->app->instance(EntitlementGate::class, $gate);

        $base = DB::transactionLevel();

        app(TransferHouseholdOwnership::class)->execute($household, $owner, $receiver->id);

        $this->assertSame([$base + 1], $gate->capacityLevels);
        $this->assertSame([1], $gate->incomingAmounts);
        $this->assertSame($receiver->user_id, $household->refresh()->owner_id);
    }

    public function test_a_capacity_denial_refuses_the_transfer_and_keeps_the_current_owner(): void
    {
        [$owner, $household, $receiver] = $this->householdWithOwnerAndAdmin();
        $gate = new RecordingEntitlementGate;
        $gate->denyAfterCapacityCalls = 0; // Every capacity assertion is refused.
        $this->app->instance(EntitlementGate::class, $gate);

        try {
            app(TransferHouseholdOwnership::class)->execute($household, $owner, $receiver->id);
            $this->fail('A receiver without capacity must refuse the transfer.');
        } catch (EntitlementQuotaExceededException) {
            // Expected: taking ownership consumes the receiver's capacity.
        }

        $this->assertSame($owner->id, $household->refresh()->owner_id);
        $this->assertGreaterThanOrEqual(
            1,
            $household->members()->where('role', HouseholdRole::Admin->value)->count(),
            'A refused transfer must leave an administrator in the household.',
        );
    }

    private function householdData(string $name): CreateHouseholdData
    {
        return CreateHouseholdData::fromArray(['name' => $name]);
    }

    /**
     * Build a household owned by one user with a second admin member who can
     * receive ownership.
     *
     * @return array{0: User, 1: Household, 2: HouseholdMember}
     */
    private function householdWithOwnerAndAdmin(): array
    {
        $owner = User::factory()->create();
        $household = Household::factory()->create(['created_by' => $owner->id]);
        HouseholdMember::factory()->create([
            'household_id' => $household->id,
            'user_id' => $owner->id,
            'role' => HouseholdRole::Admin,
        ]);
        $receiver = HouseholdMember::factory()->create([
            'household_id' => $household->id,
            'role' => HouseholdRole::Admin,
        ]);

        return [$owner, $household, $receiver];
    }
}
