<?php

namespace App\Domains\Asset\Database\Factories\AssetInfos;

use App\Domains\Asset\Models\AssetInfos\RealEstateAssetInfo;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RealEstateAssetInfo>
 */
class RealEstateAssetInfoFactory extends Factory
{
    protected $model = RealEstateAssetInfo::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'isin' => fake()->uuid(),
        ];
    }
}
