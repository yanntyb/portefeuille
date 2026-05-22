<?php

namespace App\Domains\Asset\Factories\Assets;

use App\Domains\Asset\Factories\AssetPriceFactory;
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
        return $this;
    }

    /**
     * @return Factory
     */
    abstract protected function infos();

    /**
     * @param  callable(Factory): Factory  $configure  Closure to configure info factory
     */
    public function withInfos(callable $configure): static
    {
        return $this->afterCreating(function (Asset $asset) use ($configure) {
            $factory = $configure($this->infos());

            $factory->create([
                'asset_id' => $asset->id,
            ]);
        });
    }

    /**
     * @param  callable(AssetPriceFactory): AssetPriceFactory  $configure  Closure to configure price factory
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
