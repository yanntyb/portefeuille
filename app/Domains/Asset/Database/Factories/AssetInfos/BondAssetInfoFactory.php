<?php

namespace App\Domains\Asset\Database\Factories\AssetInfos;

use App\Domains\Asset\Models\AssetInfos\BondAssetInfo;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BondAssetInfo>
 */
class BondAssetInfoFactory extends Factory
{
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
