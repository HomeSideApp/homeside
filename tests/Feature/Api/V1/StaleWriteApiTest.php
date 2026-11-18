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
 * Covers the stale-write contract of the offline update routes: a device that
 * edited a row offline pushes the version it based the edit on, and the server
 * refuses the write when the row moved on, returning the live row to merge.
 */
class StaleWriteApiTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Household $household;

    private ShoppingList $list;

    private ListItem $item;

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

        $this->list = ShoppingList::factory()->create([
            'created_by' => $this->user->id,
            'household_id' => null,
        ]);
        $this->item = ListItem::factory()->create([
            'list_id' => $this->list->id,
            'custom_name' => 'Leche',
            'quantity' => 1,
        ]);

        Sanctum::actingAs($this->user);
    }

    public function test_two_puts_with_the_same_version_make_the_second_one_stale_and_return_the_live_row(): void
    {
        $version = $this->item->updated_at->toISOString();

        // The second write has to land in a later second, or the stored version
        // and the fresh one are indistinguishable at the column's precision.
        $this->travel(5)->seconds();

        $this->putJson(
            route('api.v1.lists.items.update', [$this->list, $this->item]),
            ['quantity' => 2],
            ['Idempotency-Key' => 'stale-item-first', 'If-Unmodified-Since' => $version],
        )->assertOk()->assertJsonPath('data.quantity', 2);

        $response = $this->putJson(
            route('api.v1.lists.items.update', [$this->list, $this->item]),
            ['quantity' => 3],
            ['Idempotency-Key' => 'stale-item-second', 'If-Unmodified-Since' => $version],
        );

        $response->assertStatus(409)
            ->assertJsonPath('code', 'stale_write')
            ->assertJsonPath('data.id', $this->item->id)
            ->assertJsonPath('data.quantity', 2);

        $this->assertSame(2.0, $this->item->refresh()->quantity);
    }

    public function test_a_put_carrying_the_current_version_succeeds(): void
    {
        $this->putJson(
            route('api.v1.lists.items.update', [$this->list, $this->item]),
            ['quantity' => 4],
            ['Idempotency-Key' => 'fresh-item', 'If-Unmodified-Since' => $this->item->updated_at->toISOString()],
        )->assertOk()->assertJsonPath('data.quantity', 4);

        $this->assertSame(4.0, $this->item->refresh()->quantity);
    }

    public function test_a_household_item_put_carrying_a_stale_version_is_refused(): void
    {
        $list = ShoppingList::factory()->create([
            'created_by' => $this->user->id,
            'household_id' => $this->household->id,
        ]);
        $item = ListItem::factory()->create(['list_id' => $list->id, 'quantity' => 1]);
        $version = $item->updated_at->toISOString();

        $this->travel(5)->seconds();
        $item->update(['quantity' => 7]);

        $this->putJson(
            route('api.v1.households.lists.items.update', [$this->household, $list, $item]),
            ['quantity' => 2],
            ['Idempotency-Key' => 'stale-household-item', 'If-Unmodified-Since' => $version],
        )->assertStatus(409)
            ->assertJsonPath('code', 'stale_write')
            ->assertJsonPath('data.id', $item->id)
            ->assertJsonPath('data.quantity', 7);

        $this->assertSame(7.0, $item->refresh()->quantity);
    }

    public function test_a_delete_carrying_a_stale_version_is_refused_and_the_row_survives(): void
    {
        $version = $this->item->updated_at->toISOString();

        $this->travel(5)->seconds();
        $this->item->update(['quantity' => 9]);

        $this->deleteJson(
            route('api.v1.lists.items.destroy', [$this->list, $this->item]),
            [],
            ['Idempotency-Key' => 'stale-delete', 'If-Unmodified-Since' => $version],
        )->assertStatus(409)
            ->assertJsonPath('code', 'stale_write')
            ->assertJsonPath('data.id', $this->item->id);

        $this->assertDatabaseHas('list_items', ['id' => $this->item->id, 'deleted_at' => null]);
    }

    public function test_a_delete_without_a_version_still_deletes(): void
    {
        $this->deleteJson(
            route('api.v1.lists.items.destroy', [$this->list, $this->item]),
            [],
            ['Idempotency-Key' => 'unversioned-delete'],
        )->assertNoContent();

        $this->assertSoftDeleted('list_items', ['id' => $this->item->id]);
    }

    public function test_a_put_without_a_version_still_updates(): void
    {
        $this->putJson(
            route('api.v1.lists.items.update', [$this->list, $this->item]),
            ['quantity' => 6],
            ['Idempotency-Key' => 'unversioned-update'],
        )->assertOk()->assertJsonPath('data.quantity', 6);

        $this->assertSame(6.0, $this->item->refresh()->quantity);
    }

    public function test_the_version_may_travel_as_an_updated_at_field(): void
    {
        $version = $this->item->updated_at->toISOString();

        $this->travel(5)->seconds();
        $this->item->update(['quantity' => 11]);

        $this->putJson(
            route('api.v1.lists.items.update', [$this->list, $this->item]),
            ['quantity' => 12, 'updated_at' => $version],
            ['Idempotency-Key' => 'body-version'],
        )->assertStatus(409)
            ->assertJsonPath('code', 'stale_write')
            ->assertJsonPath('data.quantity', 11);

        $this->assertSame(11.0, $this->item->refresh()->quantity);
    }

    public function test_the_version_may_travel_in_the_query_string(): void
    {
        $version = $this->item->updated_at->toISOString();

        $this->travel(5)->seconds();
        $this->item->update(['quantity' => 21]);

        $this->putJson(
            route('api.v1.lists.items.update', [$this->list, $this->item]).'?updated_at='.urlencode($version),
            ['quantity' => 22],
            ['Idempotency-Key' => 'query-version'],
        )->assertStatus(409)->assertJsonPath('code', 'stale_write');

        $this->assertSame(21.0, $this->item->refresh()->quantity);
    }

    public function test_a_malformed_version_header_is_rejected(): void
    {
        $this->putJson(
            route('api.v1.lists.items.update', [$this->list, $this->item]),
            ['quantity' => 8],
            ['Idempotency-Key' => 'malformed-header', 'If-Unmodified-Since' => 'not-a-date'],
        )->assertStatus(422)->assertJsonValidationErrors('If-Unmodified-Since');

        $this->assertSame(1.0, $this->item->refresh()->quantity);
    }

    public function test_a_malformed_version_field_is_rejected(): void
    {
        $this->putJson(
            route('api.v1.lists.items.update', [$this->list, $this->item]),
            ['quantity' => 8, 'updated_at' => 'yesterday-ish'],
            ['Idempotency-Key' => 'malformed-field'],
        )->assertStatus(422)->assertJsonValidationErrors('updated_at');

        $this->assertSame(1.0, $this->item->refresh()->quantity);
    }

    public function test_an_idempotent_replay_short_circuits_before_the_staleness_check(): void
    {
        $version = $this->item->updated_at->toISOString();
        $headers = ['Idempotency-Key' => 'replayed-write', 'If-Unmodified-Since' => $version];
        $payload = ['quantity' => 5];

        $first = $this->putJson(route('api.v1.lists.items.update', [$this->list, $this->item]), $payload, $headers);

        $this->travel(5)->seconds();

        $second = $this->putJson(route('api.v1.lists.items.update', [$this->list, $this->item]), $payload, $headers);

        $first->assertOk();
        $second->assertOk()->assertExactJson($first->json());
    }

    public function test_a_personal_transaction_put_carrying_a_stale_version_returns_the_live_resource(): void
    {
        $transaction = EconomicTransaction::factory()->createdBy($this->user)->create([
            'household_id' => null,
            'title' => 'Cena',
            'amount_minor' => 2000,
        ]);
        $version = $transaction->updated_at->toISOString();

        $this->travel(5)->seconds();
        $transaction->update(['title' => 'Cena editada en otro sitio']);

        $this->putJson(
            route('api.v1.economy.me.transactions.update', $transaction),
            ['title' => 'Cena local'],
            ['Idempotency-Key' => 'stale-personal-transaction', 'If-Unmodified-Since' => $version],
        )->assertStatus(409)
            ->assertJsonPath('code', 'stale_write')
            ->assertJsonPath('data.id', $transaction->id)
            ->assertJsonPath('data.title', 'Cena editada en otro sitio');

        $this->assertSame('Cena editada en otro sitio', $transaction->refresh()->title);
    }

    public function test_a_household_transaction_delete_carrying_a_stale_version_keeps_the_row(): void
    {
        $transaction = EconomicTransaction::factory()->forHousehold($this->household)->createdBy($this->user)->create([
            'title' => 'Compra',
            'amount_minor' => 3000,
        ]);
        $version = $transaction->updated_at->toISOString();

        $this->travel(5)->seconds();
        $transaction->update(['title' => 'Compra editada en otro sitio']);

        $this->deleteJson(
            route('api.v1.households.economy.transactions.destroy', [$this->household, $transaction]),
            [],
            ['Idempotency-Key' => 'stale-household-transaction', 'If-Unmodified-Since' => $version],
        )->assertStatus(409)
            ->assertJsonPath('code', 'stale_write')
            ->assertJsonPath('data.id', $transaction->id);

        $this->assertDatabaseHas('economic_transactions', ['id' => $transaction->id, 'deleted_at' => null]);
    }

    public function test_a_transaction_the_caller_cannot_write_is_not_disclosed_by_the_stale_answer(): void
    {
        $foreign = EconomicTransaction::factory()->createdBy(User::factory()->create())->create([
            'household_id' => null,
            'title' => 'Ajena',
        ]);

        $this->putJson(
            route('api.v1.economy.me.transactions.update', $foreign),
            ['title' => 'Mía'],
            ['Idempotency-Key' => 'foreign-transaction', 'If-Unmodified-Since' => '2015-10-21T07:28:00+00:00'],
        )->assertForbidden()->assertJsonMissingPath('data');

        $this->assertSame('Ajena', $foreign->refresh()->title);
    }
}
