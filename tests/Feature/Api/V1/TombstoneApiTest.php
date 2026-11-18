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
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Covers the tombstone contract: a deletion keeps the row, hides it from every
 * read, and advances `updated_at` so an `updated_at`-ordered delta sees it.
 */
class TombstoneApiTest extends TestCase
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

    public function test_deleting_an_item_tombstones_it_and_advances_updated_at(): void
    {
        $list = $this->householdList();
        $item = ListItem::factory()->create(['list_id' => $list->id]);
        $updatedBefore = $item->updated_at;

        $this->travel(2)->minutes();

        $this->deleteJson(route('api.v1.households.lists.items.destroy', [$this->household, $list, $item]), [], ['Idempotency-Key' => 'delete-item'])
            ->assertNoContent();

        $row = DB::table('list_items')->where('id', $item->id)->first();

        $this->assertNotNull($row, 'The deleted item must keep its row as a tombstone.');
        $this->assertNotNull($row->deleted_at);
        $this->assertTrue(
            Carbon::parse($row->updated_at)->greaterThan($updatedBefore),
            'A tombstone must advance updated_at or an updated_at-ordered delta loses the deletion.',
        );
        $this->assertNull(ListItem::query()->find($item->id));
    }

    public function test_deleted_item_disappears_from_the_list_resource(): void
    {
        $list = $this->householdList();
        $keptItem = ListItem::factory()->create(['list_id' => $list->id]);
        $removedItem = ListItem::factory()->create(['list_id' => $list->id]);

        $this->deleteJson(route('api.v1.households.lists.items.destroy', [$this->household, $list, $removedItem]), [], ['Idempotency-Key' => 'delete-removed-item'])
            ->assertNoContent();

        $this->getJson(route('api.v1.households.lists.show', [$this->household, $list]))
            ->assertOk()
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.id', $keptItem->id);
    }

    public function test_deleting_a_list_tombstones_its_items(): void
    {
        $list = $this->householdList();
        $items = ListItem::factory()->count(2)->create(['list_id' => $list->id]);
        $itemsUpdatedBefore = $items->mapWithKeys(fn (ListItem $item): array => [$item->id => $item->updated_at]);

        $this->travel(2)->minutes();

        $this->deleteJson(route('api.v1.households.lists.destroy', [$this->household, $list]), [], ['Idempotency-Key' => 'delete-list'])
            ->assertNoContent();

        $this->assertNotNull(DB::table('shopping_lists')->where('id', $list->id)->value('deleted_at'));

        $rows = DB::table('list_items')->where('list_id', $list->id)->get();

        $this->assertCount(2, $rows);

        foreach ($rows as $row) {
            $this->assertNotNull($row->deleted_at);
            $this->assertTrue(
                Carbon::parse($row->updated_at)->greaterThan($itemsUpdatedBefore[$row->id]),
                'Cascaded item tombstones must advance updated_at too.',
            );
        }

        $this->getJson(route('api.v1.households.lists.index', $this->household))
            ->assertOk()
            ->assertJsonMissing(['id' => $list->id]);
    }

    public function test_deleting_a_transaction_tombstones_it(): void
    {
        $transaction = EconomicTransaction::factory()->private()->createdBy($this->user)->create();
        $updatedBefore = $transaction->updated_at;

        $this->travel(2)->minutes();

        $this->deleteJson(route('api.v1.economy.me.transactions.destroy', $transaction), [], ['Idempotency-Key' => 'delete-transaction'])
            ->assertNoContent();

        $row = DB::table('economic_transactions')->where('id', $transaction->id)->first();

        $this->assertNotNull($row);
        $this->assertNotNull($row->deleted_at);
        $this->assertTrue(Carbon::parse($row->updated_at)->greaterThan($updatedBefore));

        $this->getJson(route('api.v1.economy.me.transactions.index'))
            ->assertOk()
            ->assertJsonMissing(['id' => $transaction->id]);
    }

    private function householdList(): ShoppingList
    {
        return ShoppingList::factory()->create([
            'created_by' => $this->user->id,
            'household_id' => $this->household->id,
        ]);
    }
}
