<?php

namespace Tests\Unit;

use App\Enums\HouseholdRole;
use App\Models\Household;
use App\Models\HouseholdMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HouseholdTest extends TestCase
{
    use RefreshDatabase;

    public function test_household_is_admin_returns_true_for_admin(): void
    {
        $user = User::factory()->create();
        $household = Household::factory()->create([
            'created_by' => $user->id,
        ]);
        HouseholdMember::create([
            'household_id' => $household->id,
            'user_id' => $user->id,
            'role' => HouseholdRole::Admin,
            'joined_at' => now(),
        ]);

        $this->assertTrue($household->isAdmin($user));
    }

    public function test_household_is_admin_returns_false_for_member(): void
    {
        $user = User::factory()->create();
        $household = Household::factory()->create([
            'created_by' => $user->id,
        ]);
        HouseholdMember::create([
            'household_id' => $household->id,
            'user_id' => $user->id,
            'role' => HouseholdRole::Member,
            'joined_at' => now(),
        ]);

        $this->assertFalse($household->isAdmin($user));
    }

    public function test_household_is_member_returns_true_for_member(): void
    {
        $user = User::factory()->create();
        $household = Household::factory()->create([
            'created_by' => $user->id,
        ]);
        HouseholdMember::create([
            'household_id' => $household->id,
            'user_id' => $user->id,
            'role' => HouseholdRole::Member,
            'joined_at' => now(),
        ]);

        $this->assertTrue($household->isMember($user));
    }

    public function test_household_is_member_returns_false_for_non_member(): void
    {
        $user = User::factory()->create();
        $household = Household::factory()->create([
            'created_by' => $user->id,
        ]);

        $this->assertFalse($household->isMember($user));
    }

    public function test_generate_invite_code_is_unique(): void
    {
        $household = Household::factory()->create();

        $code1 = $household->generateInviteCode();
        $code2 = $household->generateInviteCode();

        $this->assertNotEquals($code1, $code2);
        $this->assertEquals(8, strlen($code1));
    }
}
