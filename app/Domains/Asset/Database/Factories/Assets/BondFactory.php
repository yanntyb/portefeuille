<?php

namespace App\Domains\Asset\Database\Factories\Assets;

use App\Domains\Asset\Database\Factories\AssetInfos\BondAssetInfoFactory;
use App\Domains\Asset\Enums\AssetType;
use App\Domains\Asset\Models\Assets\Bond;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Bond>
 */
class BondFactory extends Factory
{
    protected $model = Bond::class;

    public function configure(): static
    {
        return $this->afterCreating(function (Bond $bond) {
            BondAssetInfoFactory::new()->create([
                'asset_id' => $bond->id,
            ]);
        });
    }

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'name' => fake()->company().' Bond',
            'type' => AssetType::Bond->value,
        ];
    }
}
