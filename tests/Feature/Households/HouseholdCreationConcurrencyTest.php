<?php

namespace Tests\Feature\Households;

use App\Actions\Households\CreateHousehold;
use App\Contracts\EntitlementGate;
use App\Data\Households\CreateHouseholdData;
use App\Exceptions\EntitlementQuotaExceededException;
use App\Models\Household;
use App\Models\User;
use App\Support\PlatformAccess;
use HomeSide\Saas\Database\Seeders\SaasPlanSeeder;
use HomeSide\Saas\SaasServiceProvider;
use HomeSide\Saas\Services\SaasEntitlementGate;
use HomeSide\Saas\Support\FeatureGate;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Real, two-process concurrency proof for the last available ownership slot.
 *
 * This class uses DatabaseTruncation instead of RefreshDatabase on purpose: the
 * winning creation must commit a TOP-LEVEL transaction, because the package's
 * billing-account row lock is released only on commit. Inside RefreshDatabase's
 * wrapping transaction every creation would be a savepoint and the loser would
 * block forever instead of observing the committed count.
 *
 * The scenario drives the REAL entitlement port (SaasEntitlementGate ->
 * FeatureGate -> billing account row lock); it skips on a Community-only
 * checkout and when pcntl is unavailable.
 */
class HouseholdCreationConcurrencyTest extends TestCase
{
    use DatabaseTruncation;

    protected function setUp(): void
    {
        parent::setUp();

        // DatabaseTruncation only clears tables in setUp, and the suite mixes
        // truncation with RefreshDatabase classes, so leave the database clean
        // for whatever runs next.
        $this->beforeApplicationDestroyed(fn () => $this->truncateDatabaseTables());
    }

    public function test_two_concurrent_creations_at_the_last_slot_yield_exactly_one_household(): void
    {
        if (! function_exists('pcntl_fork')) {
            $this->markTestSkipped('The pcntl extension is required to race two real creation attempts.');
        }

        if (! class_exists(SaasServiceProvider::class)) {
            $this->markTestSkipped('The homeside/saas package is not installed.');
        }

        $this->seed(SaasPlanSeeder::class);
        config(['saas.enabled' => true]);

        $this->assertInstanceOf(
            SaasEntitlementGate::class,
            app(EntitlementGate::class),
            'The concurrency scenario must drive the real package port, not a test double.',
        );

        $user = User::factory()->create();
        $cap = $this->householdCap($user);
        $this->assertGreaterThanOrEqual(1, $cap, 'The seeded plan must allow owning at least one household.');

        // Fill every slot but one, so the race is for the last available slot.
        for ($i = 1; $i < $cap; $i++) {
            Household::factory()->create(['created_by' => $user->id]);
        }

        $this->assertSame($cap - 1, Household::query()->where('owner_id', $user->id)->count());

        // Model the race window truthfully: the request has already rendered the
        // combined presentation decision (a page render or permissions
        // serialization did it), so from the UI's point of view the create
        // control is visible. The only remaining decision is the authoritative
        // assertion inside the creation transaction, which is what must
        // serialize. This read also creates the billing-account row both racers
        // will contend for.
        $this->assertTrue(app(PlatformAccess::class)->mayCreateHouseholdNow($user));

        // Drop the live connection before forking: parent and child each open
        // their own database session, so only the row lock serializes them.
        DB::purge();

        $child = pcntl_fork();

        if ($child === -1) {
            $this->fail('Unable to fork a second process for the concurrency test.');
        }

        if ($child === 0) {
            exit($this->attemptCreation($user));
        }

        $parentOutcome = $this->attemptCreation($user);
        pcntl_waitpid($child, $status);
        $childOutcome = pcntl_wexitstatus($status);

        $outcomes = [$parentOutcome, $childOutcome];
        sort($outcomes);

        // 0 = created, 2 = refused by the documented EntitlementQuotaExceededException.
        $this->assertSame(
            [0, 2],
            $outcomes,
            'Exactly one racer must win the last slot and the other must be refused with the documented quota outcome.',
        );
        $this->assertSame(
            $cap,
            Household::query()->where('owner_id', $user->id)->count(),
            'Exactly one household may be added at the last available slot.',
        );
    }

    /**
     * One real creation attempt.
     *
     * @return int 0 = created, 2 = refused by quota, 3 = any other failure.
     */
    private function attemptCreation(User $user): int
    {
        try {
            app(CreateHousehold::class)->execute(
                CreateHouseholdData::fromArray(['name' => 'Concurrent '.bin2hex(random_bytes(4))]),
                $user,
            );

            return 0;
        } catch (EntitlementQuotaExceededException) {
            return 2;
        } catch (\Throwable) {
            return 3;
        }
    }

    private function householdCap(User $user): int
    {
        $quota = app(FeatureGate::class)->for($user)->quota('households_own');

        $this->assertIsInt($quota, 'The seeded plan must define a households_own cap.');

        return $quota;
    }
}
