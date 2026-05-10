<?php

namespace App\Domains\Asset\Database\Factories\AssetInfos;

use App\Domains\Asset\Models\AssetInfos\SavingsAssetInfo;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SavingsAssetInfo>
 */
class SavingsAssetInfoFactory extends Factory
{
    protected $model = SavingsAssetInfo::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [];
    }
}
