<?php

namespace App\Domains\Asset\Database\Factories\Assets;

use App\Domains\Asset\Database\Factories\AssetInfos\StockAssetInfoFactory;
use App\Domains\Asset\Enums\AssetType;
use App\Domains\Asset\Models\Assets\Stock;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Stock>
 */
class StockFactory extends Factory
{
    protected $model = Stock::class;

    public function configure(): static
    {
        return $this->afterCreating(function (Stock $stock) {
            StockAssetInfoFactory::new()->create([
                'asset_id' => $stock->id,
            ]);
        });
    }

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'name' => fake()->company().' Stock',
            'type' => AssetType::Stock,
        ];
    }
}
