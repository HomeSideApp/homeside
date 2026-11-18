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
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Covers the offline push batch: one request drains the device outbox by
 * re-dispatching each queued operation through the router, answering one result
 * per operation in the same order, and refusing every path whose route name is
 * not in the offline write allowlist.
 */
class SyncPushApiTest extends TestCase
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

    public function test_one_valid_one_conflicting_and_one_invalid_operation_answer_in_order(): void
    {
        $version = $this->item->updated_at->toISOString();

        // The row moved on somewhere else after the device read it.
        $this->travel(5)->seconds();
        $this->item->update(['quantity' => 7]);

        $applied = $this->operation('POST', $this->apiPath('api.v1.lists.items.store', $this->list), ['custom_name' => 'Pan']);
        $conflicting = $this->operation('PUT', $this->apiPath('api.v1.lists.items.update', [$this->list, $this->item]), [
            'quantity' => 3,
            'updated_at' => $version,
        ]);
        $invalid = $this->operation('POST', $this->apiPath('api.v1.lists.store'), []);

        $response = $this->push([$applied, $conflicting, $invalid])->assertOk();

        $results = $response->json('data.results');

        $this->assertSame(
            [$applied['op_id'], $conflicting['op_id'], $invalid['op_id']],
            array_column($results, 'op_id'),
        );

        $this->assertSame('applied', $results[0]['status']);
        $this->assertSame(201, $results[0]['status_code']);
        $this->assertSame('Pan', $results[0]['body']['data']['custom_name']);

        $this->assertSame('conflict', $results[1]['status']);
        $this->assertSame(409, $results[1]['status_code']);
        $this->assertSame('stale_write', $results[1]['body']['code']);
        $this->assertSame($this->item->id, $results[1]['body']['data']['id']);
        $this->assertSame(7.0, (float) $results[1]['body']['data']['quantity']);

        $this->assertSame('rejected', $results[2]['status']);
        $this->assertSame(422, $results[2]['status_code']);
        $this->assertArrayHasKey('name', $results[2]['body']['errors']);

        // Only the applied operation changed the database.
        $this->assertDatabaseHas('list_items', ['list_id' => $this->list->id, 'custom_name' => 'Pan']);
        $this->assertSame(2, ListItem::query()->count());
        $this->assertSame(1, ShoppingList::query()->count());
        $this->assertSame(7.0, $this->item->refresh()->quantity);
    }

    public function test_results_keep_the_request_order_when_the_middle_operation_fails(): void
    {
        $first = $this->operation('POST', $this->apiPath('api.v1.lists.items.store', $this->list), ['custom_name' => 'Uno']);
        $middle = $this->operation('DELETE', $this->apiPath('api.v1.households.destroy', $this->household));
        $last = $this->operation('POST', $this->apiPath('api.v1.lists.items.store', $this->list), ['custom_name' => 'Tres']);

        $results = $this->push([$first, $middle, $last])->assertOk()->json('data.results');

        $this->assertSame(
            [$first['op_id'], $middle['op_id'], $last['op_id']],
            array_column($results, 'op_id'),
        );
        $this->assertSame(['applied', 'rejected', 'applied'], array_column($results, 'status'));
    }

    public function test_a_rejected_operation_does_not_stop_the_ones_after_it(): void
    {
        $rejected = $this->operation('POST', $this->apiPath('api.v1.admin.users.store'), ['name' => 'Intruso']);
        $applied = $this->operation('POST', $this->apiPath('api.v1.lists.items.store', $this->list), ['custom_name' => 'Después']);

        $results = $this->push([$rejected, $applied])->assertOk()->json('data.results');

        $this->assertSame('rejected', $results[0]['status']);
        $this->assertSame(422, $results[0]['status_code']);
        $this->assertSame('applied', $results[1]['status']);
        $this->assertDatabaseHas('list_items', ['list_id' => $this->list->id, 'custom_name' => 'Después']);
    }

    public function test_an_admin_path_outside_the_allowlist_is_rejected_and_writes_nothing(): void
    {
        $users = User::query()->count();

        $results = $this->push([
            $this->operation('POST', $this->apiPath('api.v1.admin.users.store'), ['name' => 'Intruso']),
        ])->assertOk()->json('data.results');

        $this->assertSame('rejected', $results[0]['status']);
        $this->assertSame(422, $results[0]['status_code']);
        $this->assertSame($users, User::query()->count());
    }

    public function test_a_household_delete_outside_the_allowlist_is_rejected_and_keeps_the_household(): void
    {
        $results = $this->push([
            $this->operation('DELETE', $this->apiPath('api.v1.households.destroy', $this->household)),
        ])->assertOk()->json('data.results');

        $this->assertSame('rejected', $results[0]['status']);
        $this->assertDatabaseHas('households', ['id' => $this->household->id]);
    }

    public function test_a_get_operation_is_rejected(): void
    {
        $results = $this->push([
            $this->operation('GET', $this->apiPath('api.v1.lists.index')),
        ])->assertOk()->json('data.results');

        $this->assertSame('rejected', $results[0]['status']);
        $this->assertSame(422, $results[0]['status_code']);
    }

    public function test_a_method_override_cannot_reach_a_route_the_operation_did_not_name(): void
    {
        // The store URI also answers a GET (the transaction listing). A `_method`
        // override must not turn the queued write into that read route.
        $results = $this->push([
            $this->operation('POST', $this->apiPath('api.v1.economy.me.transactions.store').'?_method=GET'),
            $this->operation('POST', $this->apiPath('api.v1.lists.store'), ['name' => 'Legítima']),
        ])->assertOk()->json('data.results');

        $this->assertSame(['rejected', 'applied'], array_column($results, 'status'));
        $this->assertSame(422, $results[0]['status_code']);
        $this->assertSame(0, EconomicTransaction::query()->count());
        $this->assertDatabaseHas('shopping_lists', ['name' => 'Legítima']);
    }

    public function test_more_than_fifty_operations_reject_the_whole_request(): void
    {
        $operations = [];

        for ($index = 0; $index < 51; $index++) {
            $operations[] = $this->operation('POST', $this->apiPath('api.v1.lists.items.store', $this->list), ['custom_name' => 'Elemento '.$index]);
        }

        $this->push($operations)->assertStatus(422)->assertJsonValidationErrors('operations');

        $this->assertSame(1, ListItem::query()->count());
    }

    public function test_retrying_the_same_batch_with_the_same_key_does_not_duplicate_rows(): void
    {
        $operations = [
            $this->operation('POST', $this->apiPath('api.v1.lists.items.store', $this->list), ['custom_name' => 'Reintento']),
        ];
        $key = (string) Str::uuid();

        $first = $this->push($operations, ['Idempotency-Key' => $key])->assertOk();
        $second = $this->push($operations, ['Idempotency-Key' => $key])->assertOk();

        $second->assertExactJson($first->json());
        $this->assertSame(1, ListItem::query()->where('custom_name', 'Reintento')->count());
    }

    public function test_a_batch_authenticated_by_a_bearer_token_applies_operations(): void
    {
        $this->app['auth']->forgetGuards();
        $token = $this->user->createToken('device')->plainTextToken;

        $results = $this->postJson(
            route('api.v1.sync.push'),
            ['operations' => [
                $this->operation('POST', $this->apiPath('api.v1.lists.items.store', $this->list), ['custom_name' => 'Desde el móvil']),
            ]],
            ['Idempotency-Key' => (string) Str::uuid(), 'Authorization' => 'Bearer '.$token],
        )->assertOk()->json('data.results');

        $this->assertSame('applied', $results[0]['status']);
        $this->assertDatabaseHas('list_items', ['list_id' => $this->list->id, 'custom_name' => 'Desde el móvil']);
    }

    public function test_a_guest_is_rejected(): void
    {
        $this->app['auth']->forgetGuards();

        $this->push([
            $this->operation('POST', $this->apiPath('api.v1.lists.items.store', $this->list), ['custom_name' => 'Anónimo']),
        ])->assertUnauthorized();

        $this->assertSame(1, ListItem::query()->count());
    }

    public function test_a_plain_user_role_can_push_a_batch(): void
    {
        $results = $this->push([
            $this->operation('POST', $this->apiPath('api.v1.economy.me.transactions.store'), [
                'type' => 'expense',
                'scope' => 'personal',
                'title' => 'Cena',
                'currency' => 'EUR',
                'amount_minor' => 1500,
            ]),
        ])->assertOk()->json('data.results');

        $this->assertSame('applied', $results[0]['status']);
        $this->assertSame(201, $results[0]['status_code']);
        $this->assertSame(1, EconomicTransaction::query()->where('title', 'Cena')->count());
    }

    public function test_a_user_without_a_role_cannot_push(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->push([
            $this->operation('POST', $this->apiPath('api.v1.lists.items.store', $this->list), ['custom_name' => 'Sin rol']),
        ])->assertForbidden();

        $this->assertSame(1, ListItem::query()->count());
    }

    /**
     * Send one batch, carrying a fresh batch key unless the test pins one.
     *
     * @param  array<int, array<string, mixed>>  $operations  The queued operations.
     * @param  array<string, string>  $headers  Extra request headers.
     */
    private function push(array $operations, array $headers = []): TestResponse
    {
        return $this->postJson(
            route('api.v1.sync.push'),
            ['operations' => $operations],
            ['Idempotency-Key' => (string) Str::uuid(), ...$headers],
        );
    }

    /**
     * Build one queued operation with its own identifiers.
     *
     * @param  array<string, mixed>|null  $body  The operation payload, if any.
     * @return array<string, mixed> The operation envelope.
     */
    private function operation(string $method, string $path, ?array $body = null): array
    {
        $operation = [
            'op_id' => (string) Str::uuid(),
            'method' => $method,
            'path' => $path,
            'idempotency_key' => (string) Str::uuid(),
        ];

        if ($body !== null) {
            $operation['body'] = $body;
        }

        return $operation;
    }

    /**
     * The API-relative path a device queues, as the contract documents it.
     */
    private function apiPath(string $routeName, mixed $parameters = []): string
    {
        $path = (string) parse_url(route($routeName, $parameters), PHP_URL_PATH);

        return (string) preg_replace('#^/api#', '', $path);
    }
}
