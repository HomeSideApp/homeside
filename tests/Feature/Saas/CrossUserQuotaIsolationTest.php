<?php

namespace Tests\Feature\Saas;

use App\Contracts\EntitlementGate;
use App\Exceptions\EntitlementQuotaExceededException;
use App\Models\Household;
use App\Models\User;
use HomeSide\Saas\Database\Seeders\SaasPlanSeeder;
use HomeSide\Saas\Models\Grant;
use HomeSide\Saas\Models\Plan;
use HomeSide\Saas\Models\PlanVersion;
use HomeSide\Saas\Models\UsageEntry;
use HomeSide\Saas\SaasServiceProvider;
use HomeSide\Saas\Services\SaasEntitlementGate;
use HomeSide\Saas\Support\UsageMeter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Proves cross-user quota isolation through the app-owned EntitlementGate port
 * with the commercial package installed: one user's metered consumption must
 * never deny, credit or otherwise move another user's allowance.
 *
 * Every scenario drives the REAL bound port (SaasEntitlementGate -> FeatureGate
 * -> UsageMeter) instead of a mock, so the assertion exercises the same code the
 * application runs. The package kill switch is forced on because the Community
 * phpunit.xml ships SAAS_ENABLED=false.
 *
 * Scenarios needing the commercial package are skipped when it is absent, so a
 * Community-only install keeps a green suite.
 *
 * @see EntitlementGate
 * @see UsageMeter
 */
class CrossUserQuotaIsolationTest extends TestCase
{
    use RefreshDatabase;

    /** Metered plan metric seeded for the pro plan (cap 200 per period). */
    private const METRIC = 'ai_image_monthly';

    /** Household ownership metric seeded for the free plan (cap 2). */
    private const HOUSEHOLD_METRIC = 'households_own';

    protected function setUp(): void
    {
        parent::setUp();

        if (! class_exists(SaasServiceProvider::class)) {
            $this->markTestSkipped('The homeside/saas package is not installed.');
        }

        $this->seed(SaasPlanSeeder::class);
        config(['saas.enabled' => true]);
    }

    /**
     * Scenario 1: user A exhausts the shared plan's metric and is denied the
     * next reservation; user B, who consumed nothing, is still allowed and B's
     * counter reflects only B's own consumption.
     */
    public function test_exhausting_one_users_quota_does_not_deny_another_user(): void
    {
        $userA = $this->userWithPlan('pro');
        $userB = $this->userWithPlan('pro');

        $port = app(EntitlementGate::class);
        $meter = app(UsageMeter::class);

        $this->assertInstanceOf(
            SaasEntitlementGate::class,
            $port,
            'The isolation scenarios must drive the real package port, not a test double.',
        );

        // A reserves and commits right up to the pro cap of 200. The commit
        // takes the canonical id the port returned, never the bare caller id:
        // the ledger stores the user-scoped form, so a bare id is a no-op.
        $reservationId = $port->reserveUsage($userA, self::METRIC, 200, 'isolation-a-fills-the-cap');
        $port->commitUsage($reservationId);

        $this->assertSame(200, $meter->used($userA, self::METRIC));

        // A's next reservation is denied: the cap is per user, and A is at it.
        $this->assertQuotaDenied(
            fn () => $port->reserveUsage($userA, self::METRIC, 1, 'isolation-a-over-the-cap'),
            self::METRIC,
            200,
            200,
            1,
        );

        // B, who consumed nothing, is still allowed: A's exhaustion is not B's.
        $port->reserveUsage($userB, self::METRIC, 5, 'isolation-b-consumes');

        $this->assertSame(5, $meter->used($userB, self::METRIC), 'B own usage is what B consumed.');
        $this->assertSame(200, $meter->used($userA, self::METRIC), 'B consuming must not change A recorded usage.');
    }

    /**
     * Scenario 2: after A is exhausted, B still owns a full, independent
     * allowance and is only denied at B's own cap.
     */
    public function test_exhausting_one_users_quota_leaves_the_other_users_full_allowance_intact(): void
    {
        $userA = $this->userWithPlan('pro');
        $userB = $this->userWithPlan('pro');

        $port = app(EntitlementGate::class);
        $meter = app(UsageMeter::class);

        $reservationId = $port->reserveUsage($userA, self::METRIC, 200, 'isolation-a-max');
        $port->commitUsage($reservationId);

        // B can still spend the whole 200 of B's own cap.
        $port->reserveUsage($userB, self::METRIC, 195, 'isolation-b-most');
        $restReservationId = $port->reserveUsage($userB, self::METRIC, 5, 'isolation-b-rest');
        $port->commitUsage($restReservationId);

        $this->assertSame(200, $meter->used($userB, self::METRIC));
        $this->assertSame(200, $meter->used($userA, self::METRIC));

        // Only when B is at B's own cap is B denied.
        $this->assertQuotaDenied(
            fn () => $port->reserveUsage($userB, self::METRIC, 1, 'isolation-b-over'),
            self::METRIC,
            200,
            200,
            1,
        );

        // A's counter is untouched by any of B's activity.
        $this->assertSame(200, $meter->used($userA, self::METRIC));
    }

    /**
     * Scenario 2 (ledger attribution): every metered ledger row carries the
     * user_id of the user who reserved it, and neither counter borrows the
     * other user's rows.
     */
    public function test_each_metered_ledger_row_is_attributed_to_its_reserving_user(): void
    {
        $userA = $this->userWithPlan('pro');
        $userB = $this->userWithPlan('pro');

        $port = app(EntitlementGate::class);
        $meter = app(UsageMeter::class);

        $canonicalA = $port->reserveUsage($userA, self::METRIC, 7, 'isolation-attribution-a');
        $canonicalB = $port->reserveUsage($userB, self::METRIC, 11, 'isolation-attribution-b');

        // The stored id is the canonical, user-scoped form -- the first owner
        // included -- so the bare caller id is stored for nobody.
        $this->assertSame($userA->id.':isolation-attribution-a', $canonicalA);
        $this->assertSame($userB->id.':isolation-attribution-b', $canonicalB);

        $this->assertDatabaseHas('saas_usage_entries', [
            'operation_id' => $userA->id.':isolation-attribution-a',
            'user_id' => $userA->id,
            'metric' => self::METRIC,
            'amount' => 7,
        ]);
        $this->assertDatabaseHas('saas_usage_entries', [
            'operation_id' => $userB->id.':isolation-attribution-b',
            'user_id' => $userB->id,
            'metric' => self::METRIC,
            'amount' => 11,
        ]);

        $this->assertSame(0, $this->entriesFor('isolation-attribution-a', $userA), 'The bare caller id must match no ledger row.');
        $this->assertSame(0, $this->entriesFor($canonicalA, $userB));
        $this->assertSame(0, $this->entriesFor($canonicalB, $userA));

        $this->assertSame(7, $meter->used($userA, self::METRIC));
        $this->assertSame(11, $meter->used($userB, self::METRIC));
    }

    /**
     * Scenario 3: the household ownership quota counts only households owned
     * by the user being checked. B's owned households never consume A's
     * allowance and vice versa.
     */
    public function test_household_ownership_capacity_counts_only_the_checked_users_households(): void
    {
        $userA = $this->userWithPlan('free');
        $userB = $this->userWithPlan('free');

        $port = app(EntitlementGate::class);

        // B already owns two households: exactly the free cap.
        Household::factory()->create(['created_by' => $userB->id]);
        Household::factory()->create(['created_by' => $userB->id]);

        $this->assertSame(2, Household::query()->where('owner_id', $userB->id)->count());
        $this->assertSame(0, Household::query()->where('owner_id', $userA->id)->count());

        // A owns none: B's two owned households must not consume A's allowance.
        $port->assertHouseholdOwnershipCapacity($userA);

        // B is checked against B's own count and is denied at B's cap.
        $this->assertQuotaDenied(
            fn () => $port->assertHouseholdOwnershipCapacity($userB),
            self::HOUSEHOLD_METRIC,
            2,
            2,
            1,
        );

        // The reverse direction: A now owns two, is denied at A's cap, and
        // B's ownership count is still exactly B's own two.
        Household::factory()->create(['created_by' => $userA->id]);
        Household::factory()->create(['created_by' => $userA->id]);

        $this->assertQuotaDenied(
            fn () => $port->assertHouseholdOwnershipCapacity($userA),
            self::HOUSEHOLD_METRIC,
            2,
            2,
            1,
        );

        $this->assertSame(2, Household::query()->where('owner_id', $userA->id)->count());
        $this->assertSame(2, Household::query()->where('owner_id', $userB->id)->count());
    }

    /**
     * Scenario 4: reusing the same operation id for two different users must
     * not merge or cross-attribute their ledger entries.
     *
     * The caller id is an idempotency hint that the meter canonicalizes into a
     * user-scoped id (`UsageMeter::reserveForUser()`), so the same caller id
     * handed in by two users yields two distinct rows. The adversarial case
     * this guards is that caller-id collision: A's entry must stay A's, and
     * B's reservation must land on B's own counter.
     */
    public function test_a_shared_operation_id_never_merges_two_users_ledger_entries(): void
    {
        $userA = $this->userWithPlan('pro');
        $userB = $this->userWithPlan('pro');

        $port = app(EntitlementGate::class);
        $meter = app(UsageMeter::class);

        $sharedOperationId = 'isolation-shared-operation-id';

        // Both users hand in the SAME caller id. The meter scopes it per user,
        // so the two canonical ids differ and each commit hits only its own
        // owner's row.
        $canonicalA = $port->reserveUsage($userA, self::METRIC, 30, $sharedOperationId);
        $port->commitUsage($canonicalA);

        $this->assertSame(30, $meter->used($userA, self::METRIC));

        // B reuses A's operation id. The meter must not fold B's operation into
        // A's entry (merge) nor attribute A's row to B (cross-attribute).
        $canonicalB = $port->reserveUsage($userB, self::METRIC, 40, $sharedOperationId);

        $this->assertNotSame($canonicalA, $canonicalB, 'A shared caller id must be scoped per user.');

        $this->assertDatabaseHas('saas_usage_entries', [
            'operation_id' => $userA->id.':'.$sharedOperationId,
            'user_id' => $userA->id,
            'amount' => 30,
        ]);

        $this->assertSame(0, $this->entriesFor($canonicalA, $userB));

        $this->assertSame(30, $meter->used($userA, self::METRIC), 'B reusing the operation id must not change what A consumed.');

        $this->assertSame(
            40,
            $meter->used($userB, self::METRIC),
            'B own reservation must be charged to B, never merged into A ledger entry.',
        );
    }

    /**
     * Count ledger entries for one operation id attributed to a user.
     */
    private function entriesFor(string $operationId, User $user): int
    {
        return UsageEntry::query()
            ->where('operation_id', $operationId)
            ->where('user_id', $user->id)
            ->count();
    }

    private function userWithPlan(string $planKey): User
    {
        $user = User::factory()->create();

        Grant::create([
            'user_id' => $user->id,
            'plan_version_id' => $this->planVersion($planKey)->id,
            'starts_at' => now()->subDay(),
            'reason' => 'cross-user quota isolation test',
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

    private function assertQuotaDenied(callable $call, string $metric, int $cap, int $used, int $requested): void
    {
        try {
            $call();
        } catch (EntitlementQuotaExceededException $exception) {
            $this->assertSame($metric, $exception->metric);
            $this->assertSame($cap, $exception->cap);
            $this->assertSame($used, $exception->used);
            $this->assertSame($requested, $exception->requested);

            return;
        }

        $this->fail(sprintf(
            'Expected a [%s] quota denial (cap %d, used %d, requested %d) but the call was allowed.',
            $metric,
            $cap,
            $used,
            $requested,
        ));
    }
}
