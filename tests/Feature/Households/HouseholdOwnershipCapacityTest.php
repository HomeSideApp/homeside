<?php

namespace Tests\Feature\Households;

use App\Actions\Households\AcceptInvitation;
use App\Actions\Households\CreateHousehold;
use App\Actions\Households\TransferHouseholdOwnership;
use App\Contracts\EntitlementGate;
use App\Data\Households\CreateHouseholdData;
use App\Enums\HouseholdRole;
use App\Enums\InvitationStatus;
use App\Exceptions\EntitlementQuotaExceededException;
use App\Models\Household;
use App\Models\HouseholdInvitation;
use App\Models\HouseholdMember;
use App\Models\User;
use App\Support\PlatformAccess;
use Database\Seeders\RolesAndPermissionsSeeder;
use HomeSide\Saas\Actions\AssignGrant;
use HomeSide\Saas\Actions\RevokeActiveGrants;
use HomeSide\Saas\Database\Seeders\SaasPlanSeeder;
use HomeSide\Saas\Models\Plan;
use HomeSide\Saas\Models\PlanVersion;
use HomeSide\Saas\SaasServiceProvider;
use HomeSide\Saas\Services\SaasEntitlementGate;
use HomeSide\Saas\Support\FeatureGate;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Proves the REAL entitlement path (SaasEntitlementGate -> FeatureGate ->
 * billing account row lock) for household ownership capacity:
 *  - membership never consumes ownership capacity;
 *  - accepting an invitation changes neither roles nor subscription;
 *  - plan upgrades and downgrades are read per request and never delete data;
 *  - taking ownership consumes the receiver's capacity.
 *
 * Every assertion here depends on the commercial package being installed; the
 * class skips itself otherwise, so a Community-only checkout stays green. The
 * package is driven through its real actions (AssignGrant, RevokeActiveGrants)
 * so the resolver memo is flushed exactly as production does.
 */
class HouseholdOwnershipCapacityTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_member_of_several_households_owns_none_and_keeps_their_own_limit(): void
    {
        $this->requireSaasPackage();

        $user = User::factory()->create();

        foreach (range(1, 3) as $ignored) {
            $host = User::factory()->create();
            $household = Household::factory()->create(['created_by' => $host->id]);
            HouseholdMember::factory()->create([
                'household_id' => $household->id,
                'user_id' => $user->id,
                'role' => HouseholdRole::Member,
            ]);
        }

        $cap = $this->householdCap($user);
        $this->assertSame(3, $user->householdMemberships()->count());
        $this->assertSame(0, Household::query()->where('owner_id', $user->id)->count());

        $this->nextRequest($user);
        $this->assertTrue(
            app(PlatformAccess::class)->canCreateHouseholds($user),
            'Being a member of other people\'s households must never consume ownership capacity.',
        );

        for ($i = 0; $i < $cap; $i++) {
            $this->nextRequest($user);
            $this->assertTrue(app(PlatformAccess::class)->canCreateHouseholds($user));
            app(CreateHousehold::class)->execute($this->householdData('Owned '.$i), $user);
        }

        $this->assertSame($cap, Household::query()->where('owner_id', $user->id)->count());
        $this->assertSame(3 + $cap, $user->householdMemberships()->count(), 'Owned households add memberships, but no capacity was borrowed from them.');

        $this->nextRequest($user);
        $this->assertFalse(
            app(PlatformAccess::class)->canCreateHouseholds($user),
            'The user is now at their own ownership cap, not at someone else\'s.',
        );
    }

    public function test_accepting_an_invitation_changes_neither_roles_nor_subscription_nor_capacity(): void
    {
        $this->requireSaasPackage();
        $this->seed(RolesAndPermissionsSeeder::class);

        $host = User::factory()->create();
        $household = Household::factory()->create(['created_by' => $host->id]);
        HouseholdMember::factory()->create([
            'household_id' => $household->id,
            'user_id' => $host->id,
            'role' => HouseholdRole::Admin,
        ]);

        $joiner = User::factory()->create();
        $joiner->assignRole('user');
        $rolesBefore = $joiner->roles()->pluck('slug')->sort()->values()->all();

        $invitation = HouseholdInvitation::create([
            'household_id' => $household->id,
            'invited_by' => $host->id,
            'email' => $joiner->email,
            'status' => InvitationStatus::Pending,
            'token' => Str::random(64),
            'expires_at' => now()->addDay(),
        ]);

        app(AcceptInvitation::class)->execute($invitation->token, $joiner);

        $joiner->refresh();

        $this->assertSame($rolesBefore, $joiner->roles()->pluck('slug')->sort()->values()->all(), 'Accepting an invitation must not change roles.');
        $this->assertNull($joiner->can_create_households_override, 'Accepting an invitation must not touch the creation tri-state.');
        $this->assertSame(0, Household::query()->where('owner_id', $joiner->id)->count(), 'Accepting an invitation must not make the joiner an owner.');
        $this->assertDatabaseCount('saas_grants', 0);
        $this->assertDatabaseCount('saas_billing_accounts', 0);

        $this->nextRequest($joiner);
        $this->assertTrue(app(PlatformAccess::class)->canCreateHouseholds($joiner));

        $cap = $this->householdCap($joiner);

        for ($i = 0; $i < $cap; $i++) {
            $this->nextRequest($joiner);
            $this->assertTrue(app(PlatformAccess::class)->canCreateHouseholds($joiner));
            app(CreateHousehold::class)->execute($this->householdData('Joiner owned '.$i), $joiner);
        }

        $this->nextRequest($joiner);
        $this->assertFalse(app(PlatformAccess::class)->canCreateHouseholds($joiner));
    }

    public function test_an_upgrade_immediately_allows_a_creation_that_was_previously_refused(): void
    {
        $this->requireSaasPackage();

        $user = User::factory()->create();
        $freeCap = $this->householdCap($user);
        $this->own($user, $freeCap);

        $this->nextRequest($user);
        $this->assertFalse(app(PlatformAccess::class)->canCreateHouseholds($user));

        app(AssignGrant::class)->execute($user, $this->planVersion('plus'), null, 'phase 8 upgrade test');

        $this->nextRequest($user);
        $this->assertTrue(
            app(PlatformAccess::class)->canCreateHouseholds($user),
            'A larger plan must allow the creation the smaller plan refused, with no registration or role change.',
        );

        app(CreateHousehold::class)->execute($this->householdData('After upgrade'), $user);
        $this->assertDatabaseHas('households', ['name' => 'After upgrade', 'owner_id' => $user->id]);
    }

    public function test_a_downgrade_does_not_delete_any_existing_household(): void
    {
        $this->requireSaasPackage();

        $user = User::factory()->create();
        app(AssignGrant::class)->execute($user, $this->planVersion('plus'), null, 'phase 8 downgrade test');

        $plusCap = $this->householdCap($user);
        $this->own($user, $plusCap);

        app(RevokeActiveGrants::class)->execute($user, 'phase8-downgrade');

        $freeCap = $this->householdCap($user);

        $this->assertGreaterThan($freeCap, $plusCap, 'The scenario needs the downgraded cap to be below the owned count.');
        $this->assertSame(
            $plusCap,
            Household::query()->where('owner_id', $user->id)->count(),
            'A downgrade must never delete households the user already owns.',
        );

        $this->nextRequest($user);
        $this->assertFalse(app(PlatformAccess::class)->canCreateHouseholds($user));
    }

    public function test_after_a_downgrade_below_the_owned_count_further_creations_are_refused(): void
    {
        $this->requireSaasPackage();

        $user = User::factory()->create();
        app(AssignGrant::class)->execute($user, $this->planVersion('plus'), null, 'phase 8 downgrade refusal test');

        $plusCap = $this->householdCap($user);
        $this->own($user, $plusCap);

        app(RevokeActiveGrants::class)->execute($user, 'phase8-downgrade');

        $this->nextRequest($user);
        $this->assertFalse(app(PlatformAccess::class)->canCreateHouseholds($user));

        try {
            app(CreateHousehold::class)->execute($this->householdData('Refused after downgrade'), $user);
            $this->fail('A downgraded plan below the owned count must refuse further creations.');
        } catch (AuthorizationException) {
            // Expected: the structural gate delegates to the denied entitlement read.
        }

        $this->assertSame($plusCap, Household::query()->where('owner_id', $user->id)->count());
        $this->assertDatabaseMissing('households', ['name' => 'Refused after downgrade']);
    }

    public function test_transferring_ownership_consumes_the_receivers_capacity(): void
    {
        $this->requireSaasPackage();

        $owner = User::factory()->create();
        $receiver = User::factory()->create();
        $receiverCap = $this->householdCap($receiver);
        $this->own($receiver, $receiverCap);

        [$household, $receiverMember] = $this->householdOwnedBy($owner, $receiver);

        try {
            app(TransferHouseholdOwnership::class)->execute($household, $owner, $receiverMember->id);
            $this->fail('A receiver already at capacity must refuse the transfer.');
        } catch (EntitlementQuotaExceededException $exception) {
            $this->assertSame('households_own', $exception->metric);
            $this->assertSame(1, $exception->requested);
        }

        $this->assertSame($owner->id, $household->refresh()->owner_id, 'A refused transfer must not move ownership.');
        $this->assertSame($receiverCap, Household::query()->where('owner_id', $receiver->id)->count());
        $this->assertSame(1, Household::query()->where('owner_id', $owner->id)->count());
    }

    public function test_transferring_ownership_to_a_receiver_with_capacity_keeps_an_administrator(): void
    {
        $this->requireSaasPackage();

        $owner = User::factory()->create();
        $receiver = User::factory()->create();

        [$household, $receiverMember] = $this->householdOwnedBy($owner, $receiver);

        app(TransferHouseholdOwnership::class)->execute($household, $owner, $receiverMember->id);

        $this->assertSame($receiver->id, $household->refresh()->owner_id);
        $this->assertSame(1, Household::query()->where('owner_id', $receiver->id)->count());
        $this->assertGreaterThanOrEqual(
            1,
            $household->members()->where('role', HouseholdRole::Admin->value)->count(),
            'The household must keep at least one administrator after the transfer.',
        );
    }

    /**
     * Skip the real-gate scenarios on a Community-only checkout, and arm the
     * kill switch the Community phpunit.xml leaves off.
     */
    private function requireSaasPackage(): void
    {
        if (! class_exists(SaasServiceProvider::class)) {
            $this->markTestSkipped('The homeside/saas package is not installed.');
        }

        $this->seed(SaasPlanSeeder::class);
        config(['saas.enabled' => true]);

        $this->assertInstanceOf(
            SaasEntitlementGate::class,
            app(EntitlementGate::class),
            'These scenarios must drive the real package port, not a test double.',
        );
    }

    private function householdCap(User $user): int
    {
        $quota = app(FeatureGate::class)->for($user)->quota('households_own');

        $this->assertIsInt($quota, 'The seeded plan must define a households_own cap.');

        return $quota;
    }

    private function own(User $user, int $count): void
    {
        for ($i = 0; $i < $count; $i++) {
            Household::factory()->create(['created_by' => $user->id]);
        }
    }

    /**
     * A new HTTP request starts with empty request attributes. The package's
     * per-user entitlement memo is flushed by the real grant actions, so this
     * only has to drop the app's own per-request memo.
     */
    private function nextRequest(User $user): void
    {
        request()->attributes->remove('platform_access.household_ownership_capacity.'.$user->id);
    }

    /**
     * Build a household owned by $owner with $receiver as an admin member.
     *
     * @return array{0: Household, 1: HouseholdMember}
     */
    private function householdOwnedBy(User $owner, User $receiver): array
    {
        $household = Household::factory()->create(['created_by' => $owner->id]);
        HouseholdMember::factory()->create([
            'household_id' => $household->id,
            'user_id' => $owner->id,
            'role' => HouseholdRole::Admin,
        ]);
        $receiverMember = HouseholdMember::factory()->create([
            'household_id' => $household->id,
            'user_id' => $receiver->id,
            'role' => HouseholdRole::Admin,
        ]);

        return [$household, $receiverMember];
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

    private function householdData(string $name): CreateHouseholdData
    {
        return CreateHouseholdData::fromArray(['name' => $name]);
    }
}
