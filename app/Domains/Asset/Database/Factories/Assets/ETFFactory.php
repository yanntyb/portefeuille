<?php

namespace App\Domains\Asset\Database\Factories\Assets;

use App\Domains\Asset\Database\Factories\AssetInfos\ETFAssetInfoFactory;
use App\Domains\Asset\Enums\AssetType;
use App\Domains\Asset\Models\Assets\ETF;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ETF>
 */
class ETFFactory extends Factory
{
    protected $model = ETF::class;

    public function configure(): static
    {
        return $this->afterCreating(function (ETF $etf) {
            ETFAssetInfoFactory::new()->create([
                'asset_id' => $etf->id,
            ]);
        });
    }

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'name' => fake()->company().' ETF',
            'type' => AssetType::ETF->value,
        ];
    }
}
