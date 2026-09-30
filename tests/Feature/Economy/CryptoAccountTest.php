<?php

namespace Tests\Feature\Economy;

use App\Actions\Economy\GetCryptoPortfolio;
use App\Enums\PaymentMethodKind;
use App\Jobs\SyncCryptoPrices;
use App\Models\CryptoAsset;
use App\Models\CryptoPrice;
use App\Models\EconomicAccount;
use App\Models\EconomicTransaction;
use App\Models\PaymentMethod;
use App\Models\User;
use App\Services\Economy\CoinGeckoPriceFetcher;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CryptoAccountTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private PaymentMethod $cryptoMethod;

    private CryptoAsset $asset;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        $this->user = User::factory()->create();
        $this->user->assignRole('user');

        $this->cryptoMethod = PaymentMethod::factory()->global()->kind(
            PaymentMethodKind::Crypto
        )->create();

        $this->asset = CryptoAsset::factory()->create([
            'symbol' => 'BTC',
            'coingecko_id' => 'bitcoin',
            'decimal_places' => 8,
        ]);
    }

    public function test_crypto_account_requires_an_asset_and_matching_currency(): void
    {
        $this->actingAs($this->user)
            ->post(route('economy.me.accounts.store'), [
                'name' => 'Mi wallet',
                'payment_method_ids' => [$this->cryptoMethod->id],
                'currency' => 'BTC',
                'decimal_places' => 8,
            ])
            ->assertSessionHasErrors('crypto_asset_id');

        $this->actingAs($this->user)
            ->post(route('economy.me.accounts.store'), [
                'name' => 'Mi wallet',
                'payment_method_ids' => [$this->cryptoMethod->id],
                'crypto_asset_id' => $this->asset->id,
                'currency' => 'ETH',
                'decimal_places' => 8,
            ])
            ->assertSessionHasErrors('currency');
    }

    public function test_crypto_account_stores_satoshi_amounts_precisely(): void
    {
        $this->actingAs($this->user)
            ->post(route('economy.me.accounts.store'), [
                'name' => 'Mi wallet',
                'payment_method_ids' => [$this->cryptoMethod->id],
                'crypto_asset_id' => $this->asset->id,
                'currency' => 'BTC',
                'decimal_places' => 8,
                'initial_balance' => '0.001',
            ])
            ->assertRedirect();

        $account = EconomicAccount::query()->firstOrFail();

        $this->assertSame($this->asset->id, $account->crypto_asset_id);
        $this->assertSame(100000, $account->initial_balance_minor);
        $this->assertSame(8, $account->decimal_places);
    }

    public function test_sync_job_stores_the_fetched_prices(): void
    {
        Http::fake([
            'api.coingecko.com/*' => Http::response(['bitcoin' => ['eur' => 57000.12]], 200),
        ]);

        (new SyncCryptoPrices)->handle(new CoinGeckoPriceFetcher);

        $this->assertDatabaseHas('crypto_prices', [
            'crypto_asset_id' => $this->asset->id,
            'quote_currency' => 'EUR',
        ]);
    }

    public function test_sync_job_survives_a_provider_outage(): void
    {
        Http::fake(['api.coingecko.com/*' => Http::response('', 500)]);

        (new SyncCryptoPrices)->handle(new CoinGeckoPriceFetcher);

        $this->assertDatabaseCount('crypto_prices', 0);
    }

    public function test_portfolio_values_holdings_with_the_latest_price(): void
    {
        $account = EconomicAccount::factory()->forUser($this->user)->create([
            'crypto_asset_id' => $this->asset->id,
            'currency' => 'BTC',
            'decimal_places' => 8,
            'initial_balance_minor' => 100000, // 0.001 BTC
        ]);

        CryptoPrice::create([
            'crypto_asset_id' => $this->asset->id,
            'quote_currency' => 'EUR',
            'price' => 50000,
            'fetched_at' => now(),
        ]);

        $portfolio = (new GetCryptoPortfolio)->execute($this->user);

        // 0.001 BTC * 50000 EUR = 50 EUR = 5000 minor units
        $this->assertSame(5000, $portfolio['total_value_minor']);
        $this->assertTrue($portfolio['has_prices']);
        $this->assertSame('BTC', $portfolio['slices'][0]['symbol']);
    }

    public function test_portfolio_reports_a_null_value_without_a_price(): void
    {
        EconomicAccount::factory()->forUser($this->user)->create([
            'crypto_asset_id' => $this->asset->id,
            'currency' => 'BTC',
            'decimal_places' => 8,
            'initial_balance_minor' => 100000,
        ]);

        $portfolio = (new GetCryptoPortfolio)->execute($this->user);

        $this->assertSame(0, $portfolio['total_value_minor']);
        $this->assertFalse($portfolio['has_prices']);
        $this->assertNull($portfolio['slices'][0]['value_minor']);
    }

    public function test_portfolio_ignores_archived_accounts_and_other_users(): void
    {
        EconomicAccount::factory()->forUser($this->user)->archived()->create([
            'crypto_asset_id' => $this->asset->id,
            'currency' => 'BTC',
            'decimal_places' => 8,
            'initial_balance_minor' => 100000,
        ]);

        $other = User::factory()->create();
        $other->assignRole('user');
        EconomicAccount::factory()->forUser($other)->create([
            'crypto_asset_id' => $this->asset->id,
            'currency' => 'BTC',
            'decimal_places' => 8,
            'initial_balance_minor' => 500000,
        ]);

        $portfolio = (new GetCryptoPortfolio)->execute($this->user);

        $this->assertSame([], $portfolio['slices']);
    }

    public function test_crypto_transactions_keep_their_own_decimal_precision(): void
    {
        $account = EconomicAccount::factory()->forUser($this->user)->create([
            'crypto_asset_id' => $this->asset->id,
            'currency' => 'BTC',
            'decimal_places' => 8,
        ]);

        $transaction = EconomicTransaction::factory()->private()->createdBy($this->user)->create([
            'account_id' => $account->id,
            'currency' => 'BTC',
            'amount_minor' => 100000,
        ]);

        $this->assertSame(100000, $transaction->refresh()->amount_minor);
        $this->assertSame('BTC', $transaction->currency);
    }
}
