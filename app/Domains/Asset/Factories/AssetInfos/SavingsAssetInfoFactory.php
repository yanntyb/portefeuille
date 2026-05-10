<?php

namespace App\Domains\Asset\Factories\AssetInfos;

use App\Domains\Asset\Models\AssetInfos\SavingsAssetInfo;

/**
 * @extends AssetInfoFactory<SavingsAssetInfo>
 */
class SavingsAssetInfoFactory extends AssetInfoFactory
{
    /** @var class-string<SavingsAssetInfo> */
    protected $model = SavingsAssetInfo::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [];
    }
}
