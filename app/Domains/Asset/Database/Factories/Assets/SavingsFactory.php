<?php

namespace App\Domains\Asset\Database\Factories\Assets;

use App\Domains\Asset\Database\Factories\AssetInfos\SavingsAssetInfoFactory;
use App\Domains\Asset\Enums\AssetType;
use App\Domains\Asset\Models\Assets\Asset;
use App\Domains\Asset\Models\Assets\Savings;

/**
 * @extends AssetFactory<Savings>
 */
class SavingsFactory extends AssetFactory
{
    /** @var class-string<Savings> */
    protected $model = Savings::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'name' => fake()->company().' Account',
            'type' => AssetType::Savings->value,
        ];
    }

    protected function infos()
    {
        return SavingsAssetInfoFactory::new();
    }
}
