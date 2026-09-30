<?php

namespace Database\Seeders;

use App\Models\CryptoAsset;
use Illuminate\Database\Seeder;

/**
 * Class CryptoAssetSeeder
 *
 * Seeds the crypto assets supported by the economy module together with the decimal precision of
 * each network, which drives how amounts are stored in minor units.
 */
class CryptoAssetSeeder extends Seeder
{
    /**
     * Seed the crypto asset catalogue.
     *
     * @return void This seeder method does not return a value.
     */
    public function run(): void
    {
        $assets = [
            ['symbol' => 'BTC', 'name' => 'Bitcoin', 'coingecko_id' => 'bitcoin', 'decimal_places' => 8],
            ['symbol' => 'ETH', 'name' => 'Ethereum', 'coingecko_id' => 'ethereum', 'decimal_places' => 18],
            ['symbol' => 'USDT', 'name' => 'Tether', 'coingecko_id' => 'tether', 'decimal_places' => 6],
            ['symbol' => 'USDC', 'name' => 'USD Coin', 'coingecko_id' => 'usd-coin', 'decimal_places' => 6],
            ['symbol' => 'BNB', 'name' => 'BNB', 'coingecko_id' => 'binancecoin', 'decimal_places' => 8],
            ['symbol' => 'SOL', 'name' => 'Solana', 'coingecko_id' => 'solana', 'decimal_places' => 9],
            ['symbol' => 'XRP', 'name' => 'XRP', 'coingecko_id' => 'ripple', 'decimal_places' => 6],
            ['symbol' => 'ADA', 'name' => 'Cardano', 'coingecko_id' => 'cardano', 'decimal_places' => 6],
            ['symbol' => 'DOGE', 'name' => 'Dogecoin', 'coingecko_id' => 'dogecoin', 'decimal_places' => 8],
            ['symbol' => 'DOT', 'name' => 'Polkadot', 'coingecko_id' => 'polkadot', 'decimal_places' => 8],
            ['symbol' => 'LINK', 'name' => 'Chainlink', 'coingecko_id' => 'chainlink', 'decimal_places' => 8],
            ['symbol' => 'AVAX', 'name' => 'Avalanche', 'coingecko_id' => 'avalanche-2', 'decimal_places' => 8],
            ['symbol' => 'TRX', 'name' => 'TRON', 'coingecko_id' => 'tron', 'decimal_places' => 6],
            ['symbol' => 'LTC', 'name' => 'Litecoin', 'coingecko_id' => 'litecoin', 'decimal_places' => 8],
            ['symbol' => 'SHIB', 'name' => 'Shiba Inu', 'coingecko_id' => 'shiba-inu', 'decimal_places' => 8],
        ];

        foreach ($assets as $asset) {
            CryptoAsset::updateOrCreate(
                ['symbol' => $asset['symbol']],
                [...$asset, 'is_active' => true],
            );
        }
    }
}
