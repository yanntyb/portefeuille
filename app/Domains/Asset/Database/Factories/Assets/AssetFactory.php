<?php

namespace App\Domains\Asset\Database\Factories\Assets;

use App\Domains\Asset\Database\Factories\AssetPriceFactory;
use App\Domains\Asset\Models\Assets\Asset;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Asset>
 */
abstract class AssetFactory extends Factory
{
    protected $model = Asset::class;

    public function configure(): static
    {
        return $this->afterCreating(function (Asset $asset) {
            $this->infos()->create([
                'asset_id' => $asset->id,
            ]);
        });
    }

    /**
     * @return Factory
     */
    abstract protected function infos();

    /**
     * @param callable(AssetPriceFactory): AssetPriceFactory $configure Closure to configure price factory
     */
    public function withPrices(callable $configure): static
    {
        return $this->afterCreating(function (Asset $asset) use ($configure) {
            $factory = $configure(AssetPriceFactory::new());

            $factory->create([
                'asset_id' => $asset->id,
            ]);
        });
    }
}
