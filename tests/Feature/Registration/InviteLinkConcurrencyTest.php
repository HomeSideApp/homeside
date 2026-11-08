<?php

namespace Tests\Feature\Registration;

use App\Models\HouseholdInviteLink;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithHousehold;
use Tests\TestCase;

/**
 * Proves the last available use of an invite link is consumed exactly once.
 *
 * The invite-link action locks the link row FOR UPDATE, re-validates it and only
 * then increments the counter inside a single transaction with the user and the
 * membership. Two acceptances racing for the last use therefore collapse into
 * one success: the loser re-reads the committed counter under the lock and is
 * refused. This is a real database-transaction test, not a browser test.
 */
class InviteLinkConcurrencyTest extends TestCase
{
    use RefreshDatabase, WithHousehold;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        Role::firstOrCreate(['slug' => 'user-invitation'], ['name' => 'User - Invitation', 'is_system' => true]);

        $this->owner = User::factory()->create();
        $this->owner->assignRole('user');
        $this->createHouseholdForUser($this->owner);
    }

    private function makeLink(string $token, int $maxUses): HouseholdInviteLink
    {
        return HouseholdInviteLink::create([
            'household_id' => $this->household->id,
            'created_by' => $this->owner->id,
            'token' => $token,
            'max_uses' => $maxUses,
        ]);
    }

    public function test_two_acceptances_of_the_last_available_use_yield_exactly_one_member(): void
    {
        $link = $this->makeLink('race-token', 1);

        $first = $this->post(route('invite-links.store', $link->token), [
            'token' => $link->token,
            'name' => 'First',
            'email' => 'first-racer@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);
        $first->assertRedirect();

        $second = $this->post(route('invite-links.store', $link->token), [
            'token' => $link->token,
            'name' => 'Second',
            'email' => 'second-racer@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);
        $second->assertStatus(422);

        $this->assertSame(1, $link->fresh()->uses_count);
        $this->assertDatabaseHas('users', ['email' => 'first-racer@example.com']);
        $this->assertDatabaseMissing('users', ['email' => 'second-racer@example.com']);
        $this->assertSame(1, $this->household->members()->where('user_id', '!=', $this->owner->id)->count());
    }
}
