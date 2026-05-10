<?php

namespace App\Domains\Asset\Database\Factories\AssetInfos;

use App\Domains\Asset\Models\AssetInfos\ETFAssetInfo;

/**
 * @extends AssetInfoFactory<ETFAssetInfo>
 */
class ETFAssetInfoFactory extends AssetInfoFactory
{
    /** @var class-string<ETFAssetInfo> */
    protected $model = ETFAssetInfo::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        $countryPrefixes = ['FR', 'US', 'DE', 'LU', 'IE'];
        $prefix = fake()->randomElement($countryPrefixes);
        $digits = fake()->numerify('##########');

        return [
            'isin' => $prefix.$digits,
            'ticker' => fake()->lexify('????').'.PA',
        ];
    }
}
