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
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Covers the offline delta feed: an updated_at-ordered cursor across the visible
 * lists, their items, the economy transactions and the caller's households, plus
 * the tombstones of the three soft-deletable streams.
 */
class SyncChangesApiTest extends TestCase
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

    public function test_a_second_call_reports_only_the_changes_and_deletions_since_the_first_call(): void
    {
        $list = $this->personalList();
        $changedItem = $this->item($list);
        $deletedItemA = $this->item($list);
        $deletedItemB = $this->item($list);
        $changedTransaction = $this->transaction();
        $deletedTransaction = $this->transaction();

        $first = $this->sync();

        $this->assertSame([], $first['deleted']['lists']);
        $this->assertSame([], $first['deleted']['list_items']);
        $this->assertSame([], $first['deleted']['economy_transactions']);

        $this->travel(2)->minutes();

        $this->putJson(route('api.v1.lists.items.update', [$list, $changedItem]), ['is_checked' => true], ['Idempotency-Key' => 'sync-change-item'])
            ->assertOk();
        $this->putJson(route('api.v1.economy.me.transactions.update', $changedTransaction), ['title' => 'Changed title'], ['Idempotency-Key' => 'sync-change-transaction'])
            ->assertOk();
        $this->deleteJson(route('api.v1.lists.items.destroy', [$list, $deletedItemA]), [], ['Idempotency-Key' => 'sync-delete-item-a'])
            ->assertNoContent();
        $this->deleteJson(route('api.v1.lists.items.destroy', [$list, $deletedItemB]), [], ['Idempotency-Key' => 'sync-delete-item-b'])
            ->assertNoContent();
        $this->deleteJson(route('api.v1.economy.me.transactions.destroy', $deletedTransaction), [], ['Idempotency-Key' => 'sync-delete-transaction'])
            ->assertNoContent();

        $second = $this->sync(['since' => $first['cursor']]);

        $this->assertSame([$changedItem->id], array_column($second['changes']['list_items'], 'id'));
        $this->assertSame([$changedTransaction->id], array_column($second['changes']['economy_transactions'], 'id'));
        $this->assertSame([], $second['changes']['households']);
        $this->assertSame([], $second['changes']['lists']);
        $this->assertEqualsCanonicalizing([$deletedItemA->id, $deletedItemB->id], $second['deleted']['list_items']);
        $this->assertSame([$deletedTransaction->id], $second['deleted']['economy_transactions']);
        $this->assertSame([], $second['deleted']['lists']);
        $this->assertFalse($second['has_more']);
        $this->assertCount(5, $this->entries($second), 'The second call must carry exactly the five rows, and nothing else.');
    }

    public function test_the_list_item_stream_carries_what_a_device_needs_to_draw_an_item_offline(): void
    {
        $list = $this->personalList();
        $item = $this->item($list);

        $page = $this->sync();

        $row = collect($page['changes']['list_items'])->firstWhere('id', $item->id);

        // El espejo del teléfono se llena **sólo** con este delta. Si la
        // relación no viaja acá, sin red el ítem se dibuja sin nombre ni ícono,
        // y además pisa lo que ya había devuelto el POST de creación.
        $this->assertIsArray($row['product'] ?? null);
        $this->assertSame($item->product->name, $row['product']['name'] ?? null);
        $this->assertSame($item->product->icon, $row['product']['icon'] ?? null);
        $this->assertArrayHasKey('category', $row['product']);
    }

    public function test_has_more_is_false_for_a_small_page_and_true_when_the_limit_truncates(): void
    {
        $list = $this->personalList();
        $this->item($list);
        $this->item($list);
        $this->item($list);

        $full = $this->sync();

        $this->assertFalse($full['has_more']);
        $this->assertCount(5, $this->entries($full), 'Household, list and three items fit in the default page.');

        $seen = [];
        $cursor = null;
        $sawTruncatedPage = false;

        for ($pageNumber = 0; $pageNumber < 10; $pageNumber++) {
            $page = $this->sync($cursor === null ? ['limit' => 2] : ['limit' => 2, 'since' => $cursor]);
            $cursor = $page['cursor'];
            $entries = $this->entries($page);

            $this->assertLessThanOrEqual(2, count($entries));

            if ($page['has_more']) {
                $this->assertCount(2, $entries, 'A truncated page must still be a full page.');
                $sawTruncatedPage = true;
            }

            foreach ($entries as [, $id]) {
                $this->assertNotContains($id, $seen, 'A row must not repeat across pages.');
                $seen[] = $id;
            }

            if (! $page['has_more']) {
                break;
            }
        }

        $this->assertTrue($sawTruncatedPage);
        $this->assertCount(5, $seen);
    }

    public function test_replaying_the_same_cursor_returns_the_identical_page(): void
    {
        $list = $this->personalList();
        $this->item($list);
        $this->item($list);

        $firstPage = $this->sync(['limit' => 1]);

        $replay = $this->sync(['limit' => 1, 'since' => $firstPage['cursor']]);
        $again = $this->sync(['limit' => 1, 'since' => $firstPage['cursor']]);

        $this->assertCount(1, $this->entries($replay));
        $this->assertSame($replay, $again);
    }

    public function test_a_deleted_item_is_reported_as_a_tombstone_and_disappears_from_the_list(): void
    {
        $list = $this->personalList();
        $keptItem = $this->item($list);
        $deletedItem = $this->item($list);

        $cursor = $this->sync()['cursor'];

        $this->travel(2)->minutes();

        $this->deleteJson(route('api.v1.lists.items.destroy', [$list, $deletedItem]), [], ['Idempotency-Key' => 'sync-tombstone-item'])
            ->assertNoContent();

        $page = $this->sync(['since' => $cursor]);

        $this->assertContains($deletedItem->id, $page['deleted']['list_items']);
        $this->assertNotContains($deletedItem->id, array_column($page['changes']['list_items'], 'id'));

        $this->getJson(route('api.v1.lists.show', $list))
            ->assertOk()
            ->assertJsonMissing(['id' => $deletedItem->id])
            ->assertJsonPath('data.items.0.id', $keptItem->id);
    }

    public function test_deleting_a_list_reports_the_list_and_its_cascaded_items_as_deleted(): void
    {
        $list = $this->personalList();
        $items = [$this->item($list), $this->item($list)];

        $cursor = $this->sync()['cursor'];

        $this->travel(2)->minutes();

        $this->deleteJson(route('api.v1.lists.destroy', $list), [], ['Idempotency-Key' => 'sync-tombstone-list'])
            ->assertNoContent();

        $page = $this->sync(['since' => $cursor]);

        $this->assertContains($list->id, $page['deleted']['lists']);
        $this->assertNotContains($list->id, array_column($page['changes']['lists'], 'id'));
        $this->assertEqualsCanonicalizing(
            array_map(fn (ListItem $item): string => $item->id, $items),
            $page['deleted']['list_items'],
        );
    }

    public function test_a_household_the_caller_does_not_belong_to_contributes_nothing(): void
    {
        $stranger = User::factory()->create();
        $foreignHousehold = Household::factory()->create(['created_by' => $stranger->id]);
        HouseholdMember::create([
            'user_id' => $stranger->id,
            'household_id' => $foreignHousehold->id,
            'role' => 'admin',
            'joined_at' => now(),
        ]);
        $foreignList = ShoppingList::factory()->create([
            'created_by' => $stranger->id,
            'household_id' => $foreignHousehold->id,
        ]);
        $foreignItem = ListItem::factory()->create(['list_id' => $foreignList->id]);
        $foreignTransaction = EconomicTransaction::factory()
            ->forHousehold($foreignHousehold)
            ->shared()
            ->createdBy($stranger)
            ->create();

        $page = $this->sync();
        $ids = array_column($this->entries($page), 1);

        $this->assertContains($this->household->id, $ids);
        $this->assertNotContains($foreignHousehold->id, $ids);
        $this->assertNotContains($foreignList->id, $ids);
        $this->assertNotContains($foreignItem->id, $ids);
        $this->assertNotContains($foreignTransaction->id, $ids);
    }

    public function test_another_users_personal_lists_and_transactions_never_appear(): void
    {
        $other = User::factory()->create();
        $otherList = ShoppingList::factory()->create(['created_by' => $other->id]);
        $otherItem = ListItem::factory()->create(['list_id' => $otherList->id]);
        $otherTransaction = EconomicTransaction::factory()->private()->createdBy($other)->create();

        $page = $this->sync();
        $ids = array_column($this->entries($page), 1);

        $this->assertNotContains($otherList->id, $ids);
        $this->assertNotContains($otherItem->id, $ids);
        $this->assertNotContains($otherTransaction->id, $ids);
    }

    public function test_pages_follow_the_global_updated_at_order_across_streams(): void
    {
        $this->travel(1)->minutes();
        $list = $this->personalList();
        $this->travel(1)->minutes();
        $this->item($list);
        $this->travel(1)->minutes();
        $this->transaction();

        $streams = [];
        $cursor = null;

        for ($page = 0; $page < 4; $page++) {
            $pageData = $this->sync($cursor === null ? ['limit' => 1] : ['limit' => 1, 'since' => $cursor]);
            $entries = $this->entries($pageData);

            $this->assertCount(1, $entries);
            $streams[] = $entries[0][0];
            $cursor = $pageData['cursor'];
        }

        $this->assertSame(['households', 'lists', 'list_items', 'economy_transactions'], $streams);
    }

    public function test_an_exhausted_cursor_is_echoed_unchanged_when_nothing_is_emitted(): void
    {
        $list = $this->personalList();
        $this->item($list);

        $page = $this->sync(['limit' => 100]);
        $this->assertFalse($page['has_more']);

        $empty = $this->sync(['since' => $page['cursor']]);

        $this->assertSame($page['cursor'], $empty['cursor']);
        $this->assertFalse($empty['has_more']);
        $this->assertSame([], $this->entries($empty));
    }

    public function test_tombstones_paginate_through_the_same_cursor_without_repeating(): void
    {
        $list = $this->personalList();
        $items = [$this->item($list), $this->item($list), $this->item($list)];

        $cursor = $this->sync()['cursor'];

        $this->travel(2)->minutes();

        foreach ($items as $index => $item) {
            $this->deleteJson(route('api.v1.lists.items.destroy', [$list, $item]), [], ['Idempotency-Key' => 'sync-paged-tombstone-'.$index])
                ->assertNoContent();
        }

        $deletedIds = [];

        for ($pageNumber = 0; $pageNumber < 5; $pageNumber++) {
            $page = $this->sync(['since' => $cursor, 'limit' => 2]);
            $cursor = $page['cursor'];

            foreach ($page['deleted']['list_items'] as $id) {
                $this->assertNotContains($id, $deletedIds, 'A tombstone must not repeat across pages.');
                $deletedIds[] = $id;
            }

            if (! $page['has_more']) {
                break;
            }
        }

        $this->assertEqualsCanonicalizing(
            array_map(fn (ListItem $item): string => $item->id, $items),
            $deletedIds,
        );
    }

    public function test_malformed_cursors_and_out_of_range_limits_are_validation_errors(): void
    {
        $this->getJson(route('api.v1.sync.changes', ['since' => 'not-a-cursor']))
            ->assertStatus(422)
            ->assertJsonValidationErrors('since');

        $this->getJson(route('api.v1.sync.changes', ['since' => base64_encode('{"t":"not-a-timestamp","i":"x"}')]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('since');

        $this->getJson(route('api.v1.sync.changes', ['limit' => 0]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('limit');

        $this->getJson(route('api.v1.sync.changes', ['limit' => 501]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('limit');
    }

    public function test_a_plain_user_role_can_read_the_changes_feed(): void
    {
        $this->getJson(route('api.v1.sync.changes'))
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'cursor',
                    'has_more',
                    'changes' => ['households', 'lists', 'list_items', 'economy_transactions'],
                    'deleted' => ['lists', 'list_items', 'economy_transactions'],
                ],
            ]);
    }

    public function test_a_guest_is_rejected(): void
    {
        $this->app['auth']->forgetGuards();

        $this->getJson(route('api.v1.sync.changes'))->assertUnauthorized();
    }

    public function test_a_user_without_a_role_cannot_read_the_whole_database(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson(route('api.v1.sync.changes'))->assertForbidden();
    }

    /**
     * @param  array<string, mixed>  $query  The optional `since` and `limit` query parameters.
     * @return array<string, mixed> The `data` payload of the changes feed.
     */
    private function sync(array $query = []): array
    {
        return $this->getJson(route('api.v1.sync.changes', $query))
            ->assertOk()
            ->json('data');
    }

    /**
     * Flatten a page into `[stream, id]` pairs so cross-stream assertions can run.
     *
     * @param  array<string, mixed>  $page  The `data` payload of the changes feed.
     * @return array<int, array{0: string, 1: string}> The emitted rows, stream by stream.
     */
    private function entries(array $page): array
    {
        $entries = [];

        foreach (['households', 'lists', 'list_items', 'economy_transactions'] as $stream) {
            foreach ($page['changes'][$stream] ?? [] as $row) {
                $entries[] = [$stream, $row['id']];
            }
        }

        foreach (['lists', 'list_items', 'economy_transactions'] as $stream) {
            foreach ($page['deleted'][$stream] ?? [] as $id) {
                $entries[] = [$stream, $id];
            }
        }

        return $entries;
    }

    private function personalList(): ShoppingList
    {
        return ShoppingList::factory()->create([
            'name' => 'Personal '.Str::random(6),
            'created_by' => $this->user->id,
            'household_id' => null,
        ]);
    }

    private function item(ShoppingList $list): ListItem
    {
        return ListItem::factory()->create(['list_id' => $list->id]);
    }

    private function transaction(): EconomicTransaction
    {
        return EconomicTransaction::factory()->private()->createdBy($this->user)->create();
    }
}
