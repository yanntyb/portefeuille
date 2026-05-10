<?php

namespace App\Domains\Asset\Database\Factories\AssetInfos;

use App\Domains\Asset\Models\AssetInfos\ETFAssetInfo;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ETFAssetInfo>
 */
class ETFAssetInfoFactory extends Factory
{
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
