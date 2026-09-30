<?php

namespace Tests\Feature\Middleware;

use App\Enums\HouseholdRole;
use App\Models\Household;
use App\Models\HouseholdMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class EnsureUserHasHouseholdTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_without_active_household_is_redirected_to_select_page(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        $response = $this->get(route('assistant.index'));

        $response->assertRedirect(route('households.select'));
    }

    public function test_user_with_active_household_can_access_protected_routes(): void
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
        $user->update(['active_household_id' => $household->id]);
        $user->refresh();

        $this->actingAs($user);

        $response = $this->get(route('dashboard'));

        $response->assertOk();
    }

    public function test_user_with_deleted_household_is_redirected_to_select(): void
    {
        $user = User::factory()->create();
        $household = Household::factory()->create([
            'created_by' => $user->id,
        ]);
        $user->update(['active_household_id' => $household->id]);
        $user->refresh();

        $this->actingAs($user);

        $household->delete();

        $response = $this->get(route('assistant.index'));

        $response->assertRedirect(route('households.select'));
    }

    public function test_user_with_removed_membership_is_redirected_to_select(): void
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
        $user->update(['active_household_id' => $household->id]);
        $user->refresh();

        $this->actingAs($user);

        HouseholdMember::where('household_id', $household->id)
            ->where('user_id', $user->id)
            ->delete();

        $response = $this->get(route('assistant.index'));

        $response->assertRedirect(route('households.select'));
    }

    public function test_select_page_is_accessible_without_household(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        $response = $this->get(route('households.select'));

        $response->assertOk();
    }

    public function test_disabled_household_context_renders_the_personal_dashboard(): void
    {
        $user = User::factory()->create(['households_enabled' => false]);
        $household = Household::factory()->create(['created_by' => $user->id]);
        HouseholdMember::factory()->create([
            'household_id' => $household->id,
            'user_id' => $user->id,
            'role' => HouseholdRole::Admin,
        ]);

        $response = $this->actingAs($user)->get(route('dashboard'));

        // With households disabled the unified dashboard resolves to the personal variant instead
        // of redirecting, because the route no longer carries the household in its path.
        $response->assertOk()->assertInertia(fn (Assert $page): Assert => $page
            ->component('households/Dashboard')
            ->where('dashboard', null)
            ->where('household', null)
        );
    }
}
