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
 * Pins the JSON contract of the API permission endpoint.
 *
 * The web endpoint keeps answering a redirect for browser callers; the API
 * endpoint must answer JSON only, so the documented API surface never declares
 * a redirect response.
 */
class HouseholdMemberPermissionApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_permissions_endpoint_answers_json_only(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        [$owner, $household, $member] = $this->householdWithMember();

        Sanctum::actingAs($owner);

        $response = $this->patchJson(
            route('api.v1.households.members.permissions', [$household, $member]),
            [
                'access_profile' => 'reader',
                'module_access' => ['economy' => 'none'],
            ],
        );

        $response->assertOk()
            ->assertJsonPath('data.id', $member->id)
            ->assertJsonPath('data.household_id', $household->id)
            ->assertJsonPath('data.role', 'member')
            ->assertJsonPath('data.access_profile', 'reader');

        $this->assertSame(['data'], array_keys($response->json()));
        $this->assertSame([
            'contacts' => 'read',
            'economy' => 'none',
            'recipes' => 'read',
            'shopping_lists' => 'read',
        ], collect($response->json('data.module_access'))->sortKeys()->all());
        $this->assertSame('reader', $member->refresh()->access_profile->value);
        $this->assertSame('none', $member->permissions()['economy']);
    }

    public function test_permissions_endpoint_refuses_a_member_who_does_not_manage_the_household(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        [$owner, $household, $member] = $this->householdWithMember();
        $reader = User::factory()->create();
        $reader->assignRole('user');
        HouseholdMember::factory()->create([
            'household_id' => $household->id,
            'user_id' => $reader->id,
            'access_profile' => 'reader',
        ]);

        Sanctum::actingAs($reader);

        $this->patchJson(
            route('api.v1.households.members.permissions', [$household, $member]),
            ['access_profile' => 'buyer'],
        )->assertForbidden();

        $this->assertSame('member', $member->refresh()->access_profile->value);
        $this->assertSame($owner->id, $household->owner_id);
    }

    /**
     * @return array{0: User, 1: Household, 2: HouseholdMember} The owner, the household and a plain member.
     */
    private function householdWithMember(): array
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
        $member = HouseholdMember::factory()->create(['household_id' => $household->id]);

        return [$owner, $household, $member];
    }
}
