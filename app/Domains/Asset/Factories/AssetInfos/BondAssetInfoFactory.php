<?php

namespace App\Domains\Asset\Factories\AssetInfos;

use App\Domains\Asset\Models\AssetInfos\BondAssetInfo;

/**
 * @extends AssetInfoFactory<BondAssetInfo>
 */
class BondAssetInfoFactory extends AssetInfoFactory
{
    /** @var class-string<BondAssetInfo> */
    protected $model = BondAssetInfo::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'isin' => 'XS'.fake()->numerify('##############'),
            'ticker' => fake()->lexify('????'),
        ];
    }
}
