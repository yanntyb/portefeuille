<?php

namespace App\Domains\Asset\Database\Factories;

use App\Domains\Asset\Enums\AssetType;
use App\Domains\Asset\Models\AssetInfos\ETFAssetInfo;
use App\Domains\Asset\Models\Assets\ETF;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ETF>
 */
class ETFFactory extends Factory
{
    protected $model = ETF::class;

    public function configure(): static
    {
        return $this->afterCreating(function (ETF $etf) {
            $countryPrefixes = ['FR', 'US', 'DE', 'LU', 'IE'];
            $prefix = fake()->randomElement($countryPrefixes);
            $digits = fake()->numerify('##########');
            $isin = $prefix.$digits;

            ETFAssetInfo::create([
                'asset_id' => $etf->id,
                'isin' => $isin,
                'ticker' => fake()->lexify('????').'.PA',
            ]);
        });
    }

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'name' => fake()->company().' ETF',
            'type' => AssetType::ETF->value,
        ];
    }
}
