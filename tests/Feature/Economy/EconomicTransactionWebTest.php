<?php

namespace Tests\Feature\Economy;

use App\Models\CryptoAsset;
use App\Models\EconomicAccount;
use App\Models\EconomicTransaction;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class EconomicTransactionWebTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    /**
     * Prepare an authenticated user with personal economy permissions.
     *
     * @return void This setup method does not return a value.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->user = User::factory()->create();
        $this->user->assignRole('user');
    }

    /**
     * Verify that the personal transaction detail receives an unwrapped resource.
     *
     * @return void This test method does not return a value.
     */
    public function test_personal_transaction_detail_receives_unwrapped_resource(): void
    {
        $transaction = EconomicTransaction::factory()->private()->createdBy($this->user)->create();

        $response = $this->actingAs($this->user)->get(route('economy.me.show', $transaction));

        $response->assertOk()->assertInertia(fn (Assert $page): Assert => $page
            ->component('economy/Show')
            ->where('transaction.id', $transaction->id)
            ->where('transaction.created_by.id', $this->user->id)
            ->where('transaction.created_by.name', $this->user->name)
            ->missing('transaction.data')
        );
    }

    /**
     * Verify that the detail page exposes the decimal amount, not only the minor units.
     *
     * The table and the detail header read `amount`, so a resource exposing only `amount_minor`
     * would leave every amount blank while the dashboard kept working.
     *
     * @return void This test method does not return a value.
     */
    public function test_transaction_detail_exposes_the_formatted_amount(): void
    {
        $transaction = EconomicTransaction::factory()->private()->createdBy($this->user)->create([
            'amount_minor' => 4273,
        ]);

        $this->actingAs($this->user)
            ->get(route('economy.me.show', $transaction))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('economy/Show')
                ->where('transaction.amount', '42.73')
                ->where('transaction.amount_minor', 4273)
            );
    }

    /**
     * Verify that line items, taxes and participants expose their formatted amounts.
     *
     * @return void This test method does not return a value.
     */
    public function test_transaction_children_expose_their_formatted_amounts(): void
    {
        $transaction = EconomicTransaction::factory()->private()->createdBy($this->user)->create();

        $transaction->items()->create([
            'name' => 'Leche',
            'quantity' => 2,
            'unit_amount_minor' => 120,
            'subtotal_minor' => 240,
            'tax_amount_minor' => 50,
            'total_minor' => 290,
            'position' => 1,
        ]);

        $transaction->taxes()->create([
            'name' => 'IVA',
            'rate' => 2100,
            'taxable_base_minor' => 240,
            'tax_amount_minor' => 50,
        ]);

        $this->actingAs($this->user)
            ->get(route('economy.me.show', $transaction))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('economy/Show')
                ->where('transaction.items.0.total', '2.90')
                ->where('transaction.items.0.unit_amount', '1.20')
                ->where('transaction.items.0.subtotal', '2.40')
                ->where('transaction.items.0.tax_amount', '0.50')
                ->where('transaction.taxes.0.tax_amount', '0.50')
                ->where('transaction.taxes.0.taxable_base', '2.40')
            );
    }

    /**
     * Verify that a crypto account keeps its own precision in the child amounts.
     *
     * @return void This test method does not return a value.
     */
    public function test_child_amounts_follow_the_account_precision(): void
    {
        $asset = CryptoAsset::factory()->create([
            'symbol' => 'BTC',
            'decimal_places' => 8,
        ]);
        $account = EconomicAccount::factory()->forUser($this->user)->currency('BTC', 8)->create([
            'crypto_asset_id' => $asset->id,
        ]);

        $transaction = EconomicTransaction::factory()->private()->createdBy($this->user)->create([
            'account_id' => $account->id,
            'currency' => 'BTC',
            'amount_minor' => 100000,
        ]);
        $transaction->items()->create([
            'name' => 'Sats',
            'quantity' => 1,
            'unit_amount_minor' => 100000,
            'subtotal_minor' => 100000,
            'tax_amount_minor' => 0,
            'total_minor' => 100000,
            'position' => 1,
        ]);

        $this->actingAs($this->user)
            ->get(route('economy.me.show', $transaction))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('transaction.amount', '0.00100000')
                ->where('transaction.items.0.total', '0.00100000')
            );
    }

    /**
     * Verify that the personal transaction edit page receives an unwrapped resource.
     *
     * @return void This test method does not return a value.
     */
    public function test_personal_transaction_edit_receives_unwrapped_resource(): void
    {
        $transaction = EconomicTransaction::factory()->private()->createdBy($this->user)->create();

        $response = $this->actingAs($this->user)->get(route('economy.me.edit', $transaction));

        $response->assertOk()->assertInertia(fn (Assert $page): Assert => $page
            ->component('economy/Edit')
            ->where('transaction.id', $transaction->id)
            ->where('transaction.created_by.id', $this->user->id)
            ->where('transaction.items', [])
            ->where('transaction.taxes', [])
            ->where('transaction.participants', [])
            ->where('transaction.source_document', null)
            ->missing('transaction.data')
        );
    }
}
