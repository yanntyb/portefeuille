<?php

namespace App\Domains\Asset\Factories\Assets;

use App\Domains\Asset\Factories\AssetInfos\BondAssetInfoFactory;
use App\Domains\Asset\Enums\AssetType;
use App\Domains\Asset\Models\Assets\Asset;
use App\Domains\Asset\Models\Assets\Bond;

/**
 * @extends AssetFactory<Bond>
 */
class BondFactory extends AssetFactory
{
    /** @var class-string<Bond> */
    protected $model = Bond::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'name' => fake()->company().' Bond',
            'type' => AssetType::Bond->value,
        ];
    }

    protected function infos()
    {
        return BondAssetInfoFactory::new();
    }
}
