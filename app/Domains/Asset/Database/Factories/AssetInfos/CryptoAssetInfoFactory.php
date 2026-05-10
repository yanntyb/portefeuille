<?php

namespace App\Domains\Asset\Database\Factories\AssetInfos;

use App\Domains\Asset\Models\AssetInfos\CryptoAssetInfo;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CryptoAssetInfo>
 */
class CryptoAssetInfoFactory extends Factory
{
    protected $model = CryptoAssetInfo::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'ticker' => fake()->lexify('????'),
        ];
    }
}
