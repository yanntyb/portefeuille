<?php

namespace App\Domains\Asset\Factories\Assets;

use App\Domains\Asset\Enums\AssetType;
use App\Domains\Asset\Factories\AssetInfos\RealEstateAssetInfoFactory;
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

    protected function infos()
    {
        return RealEstateAssetInfoFactory::new();
    }
}
