<?php

namespace App\Domains\Asset\Database\Factories;

use App\Domains\Asset\Enums\AssetType;
use App\Domains\Asset\Models\AssetInfos\RealEstateAssetInfo;
use App\Domains\Asset\Models\Assets\RealEstate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RealEstate>
 */
class RealEstateFactory extends Factory
{
    protected $model = RealEstate::class;

    public function configure(): static
    {
        return $this->afterCreating(function (RealEstate $realEstate) {
            RealEstateAssetInfo::create([
                'asset_id' => $realEstate->id,
            ]);
        });
    }

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'name' => fake()->city().' Property',
            'type' => AssetType::RealEstate->value,
        ];
    }
}
