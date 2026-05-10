<?php

namespace App\Domains\Asset\Database\Factories\Assets;

use App\Domains\Asset\Database\Factories\AssetInfos\StockAssetInfoFactory;
use App\Domains\Asset\Enums\AssetType;
use App\Domains\Asset\Models\Assets\Asset;
use App\Domains\Asset\Models\Assets\Stock;

/**
 * @extends AssetFactory<Stock>
 */
class StockFactory extends AssetFactory
{
    /** @var class-string<Stock> */
    protected $model = Stock::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'name' => fake()->company().' Stock',
            'type' => AssetType::Stock,
        ];
    }

    protected function getAssetInfoFactory()
    {
        return StockAssetInfoFactory::new();
    }
}
