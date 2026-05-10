<?php

namespace App\Domains\Asset\Database\Factories;

use App\Domains\Asset\Enums\AssetType;
use App\Domains\Asset\Models\Stock;
use App\Domains\Asset\Models\StockAssetInfo;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Stock>
 */
class StockFactory extends Factory
{
    protected $model = Stock::class;

    public function configure(): static
    {
        return $this->afterCreating(function (Stock $stock) {
            $countryPrefixes = ['FR', 'US', 'DE', 'LU', 'IE'];
            $prefix = fake()->randomElement($countryPrefixes);
            $digits = fake()->numerify('##########');
            $isin = $prefix.$digits;

            StockAssetInfo::create([
                'asset_id' => $stock->id,
                'isin' => $isin,
                'ticker' => fake()->randomAscii(),
            ]);
        });
    }

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'name' => fake()->company().' Stock',
            'type' => AssetType::Stock,
        ];
    }
}
