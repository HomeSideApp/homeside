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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Covers the client-provided `id` contract of the offline write routes: an
 * offline client mints the UUIDv7 locally so the row it queued exists with the
 * same identity on the server, and a foreign id collides instead of overwriting.
 */
class ClientProvidedIdApiTest extends TestCase
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

    public function test_private_list_creation_keeps_the_client_provided_id(): void
    {
        $id = (string) Str::uuid7();

        $response = $this->postJson(
            route('api.v1.lists.store'),
            ['id' => $id, 'name' => 'Mercado'],
            ['Idempotency-Key' => 'client-id-private-list'],
        );

        $response->assertCreated()->assertJsonPath('data.id', $id);
        $this->assertDatabaseHas('shopping_lists', ['id' => $id, 'name' => 'Mercado', 'household_id' => null]);
    }

    public function test_household_list_creation_keeps_the_client_provided_id(): void
    {
        $id = (string) Str::uuid7();

        $response = $this->postJson(
            route('api.v1.households.lists.store', $this->household),
            ['id' => $id, 'name' => 'Compra semanal'],
            ['Idempotency-Key' => 'client-id-household-list'],
        );

        $response->assertCreated()->assertJsonPath('data.id', $id);
        $this->assertDatabaseHas('shopping_lists', ['id' => $id, 'household_id' => $this->household->id]);
    }

    public function test_private_item_creation_keeps_the_client_provided_id(): void
    {
        $list = ShoppingList::factory()->create([
            'created_by' => $this->user->id,
            'household_id' => null,
        ]);
        $id = (string) Str::uuid7();

        $response = $this->postJson(
            route('api.v1.lists.items.store', $list),
            ['id' => $id, 'custom_name' => 'Leche', 'quantity' => 2],
            ['Idempotency-Key' => 'client-id-private-item'],
        );

        $response->assertCreated()->assertJsonPath('data.id', $id);
        $this->assertDatabaseHas('list_items', ['id' => $id, 'list_id' => $list->id]);
    }

    public function test_household_item_creation_keeps_the_client_provided_id(): void
    {
        $list = ShoppingList::factory()->create([
            'created_by' => $this->user->id,
            'household_id' => $this->household->id,
        ]);
        $id = (string) Str::uuid7();

        $response = $this->postJson(
            route('api.v1.households.lists.items.store', [$this->household, $list]),
            ['id' => $id, 'custom_name' => 'Pan'],
            ['Idempotency-Key' => 'client-id-household-item'],
        );

        $response->assertCreated()->assertJsonPath('data.id', $id);
        $this->assertDatabaseHas('list_items', ['id' => $id, 'list_id' => $list->id]);
    }

    public function test_personal_transaction_creation_keeps_the_client_provided_id(): void
    {
        $id = (string) Str::uuid7();

        $response = $this->postJson(
            route('api.v1.economy.me.transactions.store'),
            [
                'id' => $id,
                'type' => 'expense',
                'scope' => 'personal',
                'title' => 'Gasto privado',
                'amount_minor' => 1050,
                'currency' => 'EUR',
            ],
            ['Idempotency-Key' => 'client-id-personal-transaction'],
        );

        $response->assertCreated()->assertJsonPath('data.id', $id);
        $this->assertDatabaseHas('economic_transactions', ['id' => $id, 'household_id' => null]);
    }

    public function test_household_transaction_creation_keeps_the_client_provided_id(): void
    {
        $id = (string) Str::uuid7();

        $response = $this->postJson(
            route('api.v1.households.economy.transactions.store', $this->household),
            [
                'id' => $id,
                'type' => 'expense',
                'scope' => 'personal',
                'title' => 'Gasto de casa',
                'amount_minor' => 990,
                'currency' => 'EUR',
            ],
            ['Idempotency-Key' => 'client-id-household-transaction'],
        );

        $response->assertCreated()->assertJsonPath('data.id', $id);
        $this->assertDatabaseHas('economic_transactions', ['id' => $id, 'household_id' => $this->household->id]);
    }

    public function test_a_second_post_with_a_taken_id_is_rejected_and_creates_no_second_row(): void
    {
        $id = (string) Str::uuid7();

        $this->postJson(
            route('api.v1.lists.store'),
            ['id' => $id, 'name' => 'Mercado'],
            ['Idempotency-Key' => 'taken-id-first'],
        )->assertCreated();

        $this->postJson(
            route('api.v1.lists.store'),
            ['id' => $id, 'name' => 'Otra lista'],
            ['Idempotency-Key' => 'taken-id-second'],
        )->assertStatus(422)->assertJsonPath('code', 'id_taken');

        $this->assertSame(1, ShoppingList::withTrashed()->whereKey($id)->count());
        $this->assertDatabaseHas('shopping_lists', ['id' => $id, 'name' => 'Mercado']);
    }

    public function test_a_taken_id_on_a_tombstoned_row_is_still_reported_as_taken(): void
    {
        $id = (string) Str::uuid7();
        ShoppingList::factory()->create([
            'id' => $id,
            'created_by' => $this->user->id,
            'household_id' => null,
        ])->delete();

        $this->postJson(
            route('api.v1.lists.store'),
            ['id' => $id, 'name' => 'Reutilizada'],
            ['Idempotency-Key' => 'tombstoned-id'],
        )->assertStatus(422)->assertJsonPath('code', 'id_taken');

        $this->assertSame(1, ShoppingList::withTrashed()->whereKey($id)->count());
    }

    public function test_a_racing_insert_is_reported_as_a_taken_id_instead_of_a_server_error(): void
    {
        $id = (string) Str::uuid7();

        // The pre-check cannot see a row another device commits between the read
        // and the insert, so the unique index has to answer for the race. The raw
        // insert below rides inside the same transaction and rolls back with it.
        ShoppingList::creating(function () use ($id): void {
            DB::table('shopping_lists')->insert([
                'id' => $id,
                'name' => 'Otro dispositivo',
                'created_by' => $this->user->id,
                'household_id' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });

        $this->postJson(
            route('api.v1.lists.store'),
            ['id' => $id, 'name' => 'Mía'],
            ['Idempotency-Key' => 'racing-id'],
        )->assertStatus(422)->assertJsonPath('code', 'id_taken');
    }

    public function test_a_transaction_id_race_is_reported_as_a_taken_id(): void
    {
        $id = (string) Str::uuid7();

        EconomicTransaction::creating(function () use ($id): void {
            DB::table('economic_transactions')->insert([
                'id' => $id,
                'household_id' => null,
                'created_by' => $this->user->id,
                'type' => 'expense',
                'scope' => 'personal',
                'title' => 'Otro dispositivo',
                'amount_minor' => 100,
                'currency' => 'EUR',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });

        $this->postJson(
            route('api.v1.economy.me.transactions.store'),
            [
                'id' => $id,
                'type' => 'expense',
                'scope' => 'personal',
                'title' => 'Mía',
                'amount_minor' => 500,
                'currency' => 'EUR',
            ],
            ['Idempotency-Key' => 'racing-transaction-id'],
        )->assertStatus(422)->assertJsonPath('code', 'id_taken');
    }

    public function test_retrying_the_same_operation_with_the_same_key_replays_the_first_response(): void
    {
        $id = (string) Str::uuid7();
        $headers = ['Idempotency-Key' => 'client-id-replay'];
        $payload = ['id' => $id, 'name' => 'Mercado'];

        $first = $this->postJson(route('api.v1.lists.store'), $payload, $headers);
        $second = $this->postJson(route('api.v1.lists.store'), $payload, $headers);

        $first->assertCreated()->assertJsonPath('data.id', $id);
        $second->assertCreated()->assertJsonPath('data.id', $id);
        $this->assertSame(1, ShoppingList::withTrashed()->whereKey($id)->count());
    }

    public function test_a_malformed_id_is_rejected_by_validation(): void
    {
        $this->postJson(
            route('api.v1.lists.store'),
            ['id' => 'not-a-uuid', 'name' => 'Mercado'],
            ['Idempotency-Key' => 'malformed-id'],
        )->assertStatus(422)->assertJsonValidationErrors('id');

        $this->assertSame(0, ShoppingList::withTrashed()->count());
    }

    public function test_a_malformed_transaction_id_is_rejected_by_validation(): void
    {
        $this->postJson(
            route('api.v1.economy.me.transactions.store'),
            [
                'id' => '1234',
                'type' => 'expense',
                'scope' => 'personal',
                'title' => 'Gasto',
                'amount_minor' => 500,
                'currency' => 'EUR',
            ],
            ['Idempotency-Key' => 'malformed-transaction-id'],
        )->assertStatus(422)->assertJsonValidationErrors('id');

        $this->assertSame(0, EconomicTransaction::withTrashed()->count());
    }

    public function test_requests_without_an_id_keep_working_with_a_generated_one(): void
    {
        $response = $this->postJson(
            route('api.v1.lists.store'),
            ['name' => 'Sin id'],
            ['Idempotency-Key' => 'no-client-id'],
        );

        $response->assertCreated();
        $this->assertTrue(Str::isUuid((string) $response->json('data.id')));
        $this->assertSame(1, ShoppingList::withTrashed()->count());

        $item = $this->postJson(
            route('api.v1.lists.items.store', ShoppingList::query()->firstOrFail()),
            ['custom_name' => 'Sin id'],
            ['Idempotency-Key' => 'no-client-id-item'],
        );

        $item->assertCreated();
        $this->assertTrue(Str::isUuid((string) $item->json('data.id')));
        $this->assertSame(1, ListItem::withTrashed()->count());
    }
}
