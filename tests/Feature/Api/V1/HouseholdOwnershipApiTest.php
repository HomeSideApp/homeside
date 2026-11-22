<?php

namespace Tests\Feature\Api\V1;

use App\Enums\HouseholdRole;
use App\Models\Household;
use App\Models\HouseholdMember;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Pins the JSON contract of the API ownership-transfer endpoint.
 *
 * The web endpoint answers a redirect for browser callers; the API endpoint
 * must answer JSON only, so the documented API surface never declares a
 * redirect response. Both surfaces share the same action and form request, so
 * this test has to pass unchanged against the shared web controller and
 * against the dedicated API controller.
 */
class HouseholdOwnershipApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_ownership_transfer_endpoint_answers_json_only(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        [$owner, $household, $target] = $this->householdWithAdminMember();

        Sanctum::actingAs($owner);

        $response = $this->postJson(
            route('api.v1.households.ownership.transfer', $household),
            ['member_id' => $target->id],
        );

        $response->assertOk()
            ->assertJsonPath('data.id', $household->id)
            ->assertJsonPath('data.owner_id', $target->user_id);

        $this->assertSame(['data'], array_keys($response->json()));
        $this->assertSame($target->user_id, $household->refresh()->owner_id);
    }

    public function test_ownership_transfer_endpoint_refuses_a_member_who_does_not_own_the_household(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        [$owner, $household, $target] = $this->householdWithAdminMember();
        $intruder = HouseholdMember::factory()->create([
            'household_id' => $household->id,
            'role' => HouseholdRole::Admin,
        ]);

        Sanctum::actingAs($intruder->user);

        $this->postJson(
            route('api.v1.households.ownership.transfer', $household),
            ['member_id' => $target->id],
        )->assertForbidden();

        $this->assertSame($owner->id, $household->refresh()->owner_id);
    }

    /**
     * @return array{0: User, 1: Household, 2: HouseholdMember} The owner, the household and an admin member.
     */
    private function householdWithAdminMember(): array
    {
        $owner = User::factory()->create();
        $owner->assignRole('user');
        $household = Household::factory()->create(['created_by' => $owner->id]);
        HouseholdMember::create([
            'household_id' => $household->id,
            'user_id' => $owner->id,
            'role' => HouseholdRole::Admin,
            'joined_at' => now(),
        ]);
        $target = HouseholdMember::factory()->create([
            'household_id' => $household->id,
            'role' => HouseholdRole::Admin,
        ]);

        return [$owner, $household, $target];
    }
}
