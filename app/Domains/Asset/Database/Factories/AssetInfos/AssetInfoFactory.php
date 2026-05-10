<?php

namespace App\Domains\Asset\Database\Factories\AssetInfos;

use App\Domains\Asset\Models\AssetInfos\AssetInfo;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AssetInfo>
 */
abstract class AssetInfoFactory extends Factory
{
    protected $model = AssetInfo::class;
}
