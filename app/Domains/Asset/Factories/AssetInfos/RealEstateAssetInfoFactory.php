<?php

namespace App\Domains\Asset\Factories\AssetInfos;

use App\Domains\Asset\Models\AssetInfos\RealEstateAssetInfo;

/**
 * @extends AssetInfoFactory<RealEstateAssetInfo>
 */
class RealEstateAssetInfoFactory extends AssetInfoFactory
{
    /** @var class-string<RealEstateAssetInfo> */
    protected $model = RealEstateAssetInfo::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'isin' => fake()->uuid(),
        ];
    }
}
