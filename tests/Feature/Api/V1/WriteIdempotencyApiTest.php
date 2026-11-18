<?php

namespace Tests\Feature\Api\V1;

use App\Models\EconomicTransaction;
use App\Models\Household;
use App\Models\HouseholdMember;
use App\Models\ListItem;
use App\Models\ShoppingList;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Covers the mandatory Idempotency-Key contract of the offline write routes.
 */
class WriteIdempotencyApiTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Household $household;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        $this->user = User::factory()->create();
        $this->user->assignRole('user');

        $this->household = Household::factory()->create(['created_by' => $this->user->id]);
        HouseholdMember::create([
            'user_id' => $this->user->id,
            'household_id' => $this->household->id,
            'role' => 'admin',
            'joined_at' => now(),
        ]);
        $this->user->update(['active_household_id' => $this->household->id]);

        Sanctum::actingAs($this->user);
    }

    public function test_private_list_creation_replays_the_first_response_for_a_repeated_key(): void
    {
        $headers = ['Idempotency-Key' => 'create-private-list'];
        $payload = ['name' => 'Mercado'];

        $first = $this->postJson(route('api.v1.lists.store'), $payload, $headers);
        $second = $this->postJson(route('api.v1.lists.store'), $payload, $headers);

        $first->assertCreated();
        $second->assertCreated()->assertExactJson($first->json());
        $this->assertSame(1, ShoppingList::query()->where('name', 'Mercado')->count());
    }

    public function test_household_list_creation_replays_the_first_response_for_a_repeated_key(): void
    {
        $headers = ['Idempotency-Key' => 'create-household-list'];
        $payload = ['name' => 'Compra semanal'];

        $first = $this->postJson(route('api.v1.households.lists.store', $this->household), $payload, $headers);
        $second = $this->postJson(route('api.v1.households.lists.store', $this->household), $payload, $headers);

        $first->assertCreated();
        $second->assertCreated()->assertJsonPath('data.id', $first->json('data.id'));
        $this->assertSame(1, ShoppingList::query()->where('name', 'Compra semanal')->count());
    }

    public function test_item_creation_replays_the_first_response_for_a_repeated_key(): void
    {
        $list = ShoppingList::factory()->create([
            'created_by' => $this->user->id,
            'household_id' => $this->household->id,
        ]);
        $headers = ['Idempotency-Key' => 'add-item'];
        $payload = ['custom_name' => 'Leche', 'quantity' => 2, 'unit' => 'l'];

        $first = $this->postJson(route('api.v1.households.lists.items.store', [$this->household, $list]), $payload, $headers);
        $second = $this->postJson(route('api.v1.households.lists.items.store', [$this->household, $list]), $payload, $headers);

        $first->assertCreated();
        $second->assertCreated()->assertJsonPath('data.id', $first->json('data.id'));
        $this->assertSame(1, ListItem::query()->where('list_id', $list->id)->count());
    }

    public function test_private_transaction_creation_replays_the_first_response_for_a_repeated_key(): void
    {
        $headers = ['Idempotency-Key' => 'create-transaction'];
        $payload = [
            'type' => 'expense',
            'scope' => 'personal',
            'title' => 'Gasto privado',
            'amount_minor' => 1050,
            'currency' => 'EUR',
        ];

        $first = $this->postJson(route('api.v1.economy.me.transactions.store'), $payload, $headers);
        $second = $this->postJson(route('api.v1.economy.me.transactions.store'), $payload, $headers);

        $first->assertCreated();
        $second->assertCreated()->assertJsonPath('data.id', $first->json('data.id'));
        $this->assertSame(1, EconomicTransaction::query()->count());
    }

    public function test_reusing_a_key_with_a_different_body_conflicts_and_creates_nothing(): void
    {
        $headers = ['Idempotency-Key' => 'one-key-one-body'];

        $this->postJson(route('api.v1.lists.store'), ['name' => 'Mercado'], $headers)->assertCreated();

        $this->postJson(route('api.v1.lists.store'), ['name' => 'Farmacia'], $headers)
            ->assertConflict()
            ->assertJsonPath('code', 'idempotency_key_conflict');

        $this->assertSame(1, ShoppingList::query()->count());
    }

    public function test_keyless_writes_to_the_offline_scope_are_rejected(): void
    {
        $list = ShoppingList::factory()->create([
            'created_by' => $this->user->id,
            'household_id' => null,
        ]);
        $item = ListItem::factory()->create(['list_id' => $list->id]);

        $this->postJson(route('api.v1.lists.store'), ['name' => 'Sin clave'])
            ->assertStatus(422)
            ->assertJsonPath('code', 'idempotency_key_required');
        $this->putJson(route('api.v1.lists.update', $list), ['name' => 'Sin clave'])
            ->assertStatus(422)
            ->assertJsonPath('code', 'idempotency_key_required');
        $this->deleteJson(route('api.v1.lists.destroy', $list))
            ->assertStatus(422)
            ->assertJsonPath('code', 'idempotency_key_required');
        $this->postJson(route('api.v1.lists.items.store', $list), ['custom_name' => 'Sin clave'])
            ->assertStatus(422)
            ->assertJsonPath('code', 'idempotency_key_required');
        $this->putJson(route('api.v1.lists.items.update', [$list, $item]), ['quantity' => 3])
            ->assertStatus(422)
            ->assertJsonPath('code', 'idempotency_key_required');
        $this->deleteJson(route('api.v1.lists.items.destroy', [$list, $item]))
            ->assertStatus(422)
            ->assertJsonPath('code', 'idempotency_key_required');

        $this->assertDatabaseHas('shopping_lists', ['id' => $list->id, 'name' => $list->name]);
        $this->assertDatabaseHas('list_items', ['id' => $item->id, 'quantity' => $item->quantity]);
    }

    public function test_writes_outside_the_offline_scope_still_work_without_a_key(): void
    {
        $this->patchJson(route('api.v1.me.profile.update'), [
            'name' => 'Ada',
            'email' => 'ada@example.com',
            'locale' => 'es-ES',
        ])->assertOk()->assertJsonPath('data.name', 'Ada');
    }
}
