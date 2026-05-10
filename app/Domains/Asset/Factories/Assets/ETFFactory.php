<?php

namespace App\Domains\Asset\Factories\Assets;

use App\Domains\Asset\Factories\AssetInfos\ETFAssetInfoFactory;
use App\Domains\Asset\Enums\AssetType;
use App\Domains\Asset\Models\Assets\Asset;
use App\Domains\Asset\Models\Assets\ETF;

/**
 * @extends AssetFactory<ETF>
 */
class ETFFactory extends AssetFactory
{
    /** @var class-string<ETF> */
    protected $model = ETF::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'name' => fake()->company().' ETF',
            'type' => AssetType::ETF->value,
        ];
    }

    protected function infos()
    {
        return ETFAssetInfoFactory::new();
    }
}
