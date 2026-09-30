<?php

namespace Database\Factories;

use App\Models\CryptoAsset;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<CryptoAsset>
 */
class CryptoAssetFactory extends Factory
{
    protected $model = CryptoAsset::class;

    /**
     * Define the default state of a crypto asset.
     *
     * @return array<string, mixed> The default model attributes.
     */
    public function definition(): array
    {
        $symbol = Str::upper($this->faker->unique()->lexify('???'));
        $name = $this->faker->unique()->word();

        return [
            'symbol' => $symbol,
            'name' => Str::title($name),
            'coingecko_id' => Str::lower($name),
            'decimal_places' => 8,
            'icon' => null,
            'is_active' => true,
        ];
    }
}
