<?php

namespace App\Domains\Asset\Database\Factories\AssetInfos;

use App\Domains\Asset\Models\AssetInfos\StockAssetInfo;

/**
 * @extends AssetInfoFactory<StockAssetInfo>
 */
class StockAssetInfoFactory extends AssetInfoFactory
{
    /** @var class-string<StockAssetInfo> */
    protected $model = StockAssetInfo::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        $countryPrefixes = ['FR', 'US', 'DE', 'LU', 'IE'];
        $prefix = fake()->randomElement($countryPrefixes);
        $digits = fake()->numerify('##########');

        return [
            'isin' => $prefix.$digits,
            'ticker' => fake()->randomAscii(),
        ];
    }
}
