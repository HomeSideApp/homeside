<?php

namespace Tests\Feature\Api\V1;

use App\Enums\InvitationStatus;
use App\Models\EconomicTransaction;
use App\Models\Household;
use App\Models\HouseholdInvitation;
use App\Models\HouseholdMember;
use App\Models\ListItem;
use App\Models\ShoppingList;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MobileContractApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_401_envelope_when_dashboard_has_no_token(): void
    {
        $household = Household::factory()->create();

        $this->getJson(route('api.v1.households.dashboard', $household))
            ->assertUnauthorized()
            ->assertJsonPath('code', 'unauthenticated')
            ->assertJsonStructure(['message', 'code', 'errors']);
    }

    public function test_pending_invitations_are_paginated_without_internal_token(): void
    {
        $user = User::factory()->create();
        $inviter = User::factory()->create();
        $household = Household::factory()->create(['created_by' => $inviter->id]);
        HouseholdInvitation::create([
            'household_id' => $household->id,
            'invited_by' => $inviter->id,
            'email' => $user->email,
            'status' => InvitationStatus::Pending,
            'token' => str_repeat('a', 64),
            'expires_at' => now()->addDay(),
        ]);
        Sanctum::actingAs($user);

        $this->getJson(route('api.v1.households.invitations.index'))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.household.id', $household->id)
            ->assertJsonMissing(['token' => str_repeat('a', 64)])
            ->assertJsonStructure(['data', 'links', 'meta']);
    }

    public function test_household_configuration_includes_predefined_tags_without_database_seeding(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson(route('api.v1.households.configuration.show'))
            ->assertOk()
            ->assertJsonFragment([
                'key' => 'familia',
                'label' => 'Familia',
            ]);
    }

    public function test_dashboard_and_economy_overview_return_minor_units(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $user = User::factory()->create();
        $user->assignRole('user');
        $household = Household::factory()->create(['created_by' => $user->id]);
        HouseholdMember::create(['household_id' => $household->id, 'user_id' => $user->id, 'role' => 'admin', 'joined_at' => now()]);
        $list = ShoppingList::factory()->create(['household_id' => $household->id, 'created_by' => $user->id]);
        ListItem::factory()->unchecked()->create(['list_id' => $list->id, 'added_by' => $user->id]);
        EconomicTransaction::factory()->forHousehold($household)->createdBy($user)->create(['amount_minor' => 1234, 'currency' => 'EUR', 'occurred_at' => now()]);
        Sanctum::actingAs($user);

        $this->getJson(route('api.v1.households.dashboard', $household))
            ->assertOk()
            ->assertJsonPath('data.shopping_lists.active_lists_count', 1)
            ->assertJsonPath('data.shopping_lists.pending_items_count', 1);

        $this->getJson(route('api.v1.households.economy.overview', [$household, 'months' => 1]))
            ->assertOk()
            ->assertJsonPath('data.currency', 'EUR')
            ->assertJsonPath('data.totals.expenses_minor', 1234)
            ->assertJsonCount(1, 'data.monthly');

        $this->getJson(route('api.v1.economy.me.overview', ['months' => 1]))
            ->assertOk()
            ->assertJsonPath('data.currency', 'EUR')
            ->assertJsonPath('data.totals.own_minor', 1234)
            ->assertJsonCount(1, 'data.monthly');
    }

    public function test_quick_create_is_atomic_and_replays_the_same_response(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $user = User::factory()->create();
        $user->assignRole('user');
        $household = Household::factory()->create(['created_by' => $user->id]);
        HouseholdMember::create(['household_id' => $household->id, 'user_id' => $user->id, 'role' => 'admin', 'joined_at' => now()]);
        $list = ShoppingList::factory()->create(['household_id' => $household->id, 'created_by' => $user->id]);
        Sanctum::actingAs($user);
        $payload = ['product' => ['name' => 'Leche'], 'item' => ['quantity' => 2, 'unit' => 'l']];
        $headers = ['Idempotency-Key' => 'mobile-double-tap'];

        $first = $this->postJson(route('api.v1.households.lists.items.quick-create', [$household, $list]), $payload, $headers);
        $second = $this->postJson(route('api.v1.households.lists.items.quick-create', [$household, $list]), $payload, $headers);

        $first->assertCreated()->assertJsonPath('data.added_by_user.id', $user->id);
        $second->assertCreated()->assertExactJson($first->json());
        $this->assertSame(1, $list->items()->count());
    }

    public function test_reused_idempotency_key_with_different_payload_returns_409(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $user = User::factory()->create();
        $user->assignRole('user');
        $household = Household::factory()->create(['created_by' => $user->id]);
        HouseholdMember::create(['household_id' => $household->id, 'user_id' => $user->id, 'role' => 'admin', 'joined_at' => now()]);
        $list = ShoppingList::factory()->create(['household_id' => $household->id, 'created_by' => $user->id]);
        Sanctum::actingAs($user);
        $headers = ['Idempotency-Key' => 'same-key'];

        $this->postJson(route('api.v1.households.lists.items.quick-create', [$household, $list]), ['product' => ['name' => 'Leche'], 'item' => ['quantity' => 1]], $headers)->assertCreated();

        $this->postJson(route('api.v1.households.lists.items.quick-create', [$household, $list]), ['product' => ['name' => 'Pan'], 'item' => ['quantity' => 1]], $headers)
            ->assertConflict()
            ->assertJsonPath('code', 'idempotency_key_conflict');
        $this->assertSame(1, $list->items()->count());
    }
}
