<?php

namespace Tests\Feature\Api\V1;

use App\Models\Household;
use App\Models\HouseholdMember;
use App\Models\ListItem;
use App\Models\ShoppingList;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Covers `checked_at`, the timestamp that lets two offline clients resolve a
 * shared toggle by most-recent check instead of last-write-wins.
 */
class CheckedAtApiTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Household $household;

    private ShoppingList $list;

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
            'household_id' => $this->household->id,
        ]);

        Sanctum::actingAs($this->user);
    }

    public function test_checking_an_item_stamps_checked_at(): void
    {
        $item = ListItem::factory()->unchecked()->create(['list_id' => $this->list->id]);

        $response = $this->check($item, true, 'check-item');

        $response->assertOk()->assertJsonPath('data.is_checked', true);
        $this->assertNotNull($response->json('data.checked_at'));
        $this->assertNotNull($this->checkedAtColumn($item), 'Checking an item must stamp checked_at.');
    }

    public function test_unchecking_an_item_clears_checked_at(): void
    {
        $item = ListItem::factory()->unchecked()->create(['list_id' => $this->list->id]);
        $this->check($item, true, 'check-before-uncheck');

        $response = $this->check($item, false, 'uncheck-item');

        $response->assertOk()->assertJsonPath('data.is_checked', false);
        $this->assertNull($response->json('data.checked_at'));
        $this->assertNull($this->checkedAtColumn($item), 'Unchecking an item must clear checked_at.');
    }

    public function test_item_resource_always_carries_checked_at(): void
    {
        ListItem::factory()->unchecked()->create(['list_id' => $this->list->id]);

        $this->getJson(route('api.v1.households.lists.show', [$this->household, $this->list]))
            ->assertOk()
            ->assertJsonStructure(['data' => ['items' => [['id', 'is_checked', 'checked_at']]]])
            ->assertJsonPath('data.items.0.checked_at', null);
    }

    public function test_editing_another_field_does_not_move_checked_at(): void
    {
        $item = ListItem::factory()->unchecked()->create(['list_id' => $this->list->id]);
        $this->check($item, true, 'stamp-checked-at');
        $stamped = $this->checkedAtColumn($item);

        $this->travel(5)->minutes();

        $this->updateItem($item, ['notes' => 'Comprar el viernes'], 'edit-notes')
            ->assertOk();

        $this->assertTrue(
            Carbon::parse($stamped)->equalTo(Carbon::parse($this->checkedAtColumn($item))),
            'A plain edit must not move checked_at.',
        );
    }

    public function test_re_checking_an_already_checked_item_keeps_the_original_check_time(): void
    {
        $item = ListItem::factory()->unchecked()->create(['list_id' => $this->list->id]);
        $this->check($item, true, 'first-check');
        $stamped = $this->checkedAtColumn($item);

        $this->travel(5)->minutes();

        $this->check($item, true, 'second-check')->assertOk();

        $this->assertTrue(
            Carbon::parse($stamped)->equalTo(Carbon::parse($this->checkedAtColumn($item))),
            'Re-checking an already checked item must keep the moment it became checked.',
        );
    }

    private function check(ListItem $item, bool $checked, string $key): TestResponse
    {
        return $this->updateItem($item, ['is_checked' => $checked], $key);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function updateItem(ListItem $item, array $payload, string $key): TestResponse
    {
        return $this->putJson(
            route('api.v1.households.lists.items.update', [$this->household, $this->list, $item]),
            $payload,
            ['Idempotency-Key' => $key],
        );
    }

    private function checkedAtColumn(ListItem $item): ?string
    {
        return DB::table('list_items')->where('id', $item->id)->value('checked_at');
    }
}
