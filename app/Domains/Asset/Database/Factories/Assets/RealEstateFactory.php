<?php

namespace App\Domains\Asset\Database\Factories\Assets;

use App\Domains\Asset\Database\Factories\AssetInfos\RealEstateAssetInfoFactory;
use App\Domains\Asset\Enums\AssetType;
use App\Domains\Asset\Models\Assets\Asset;
use App\Domains\Asset\Models\Assets\RealEstate;

/**
 * @extends AssetFactory<RealEstate>
 */
class RealEstateFactory extends AssetFactory
{
    /** @var class-string<RealEstate> */
    protected $model = RealEstate::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'name' => fake()->city().' Property',
            'type' => AssetType::RealEstate->value,
        ];
    }

    protected function createAssetInfo(Asset $asset): void
    {
        RealEstateAssetInfoFactory::new()->create([
            'asset_id' => $asset->id,
        ]);
    }
}
