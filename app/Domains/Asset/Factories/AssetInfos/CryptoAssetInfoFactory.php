<?php

namespace App\Domains\Asset\Factories\AssetInfos;

use App\Domains\Asset\Models\AssetInfos\CryptoAssetInfo;

/**
 * @extends AssetInfoFactory<CryptoAssetInfo>
 */
class CryptoAssetInfoFactory extends AssetInfoFactory
{
    /** @var class-string<CryptoAssetInfo> */
    protected $model = CryptoAssetInfo::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'ticker' => fake()->lexify('????'),
        ];
    }
}
