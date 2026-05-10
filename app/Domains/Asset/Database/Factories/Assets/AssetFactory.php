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
     * @param int|\Closure $count Count or closure to configure factory
     * @param \Closure|null $configure Optional closure to configure factory
     */
    public function withPrices(int|callable $count = 1, ?callable $configure = null): static
    {
        return $this->afterCreating(function (Asset $asset) use ($count, $configure) {
            $factory = AssetPriceFactory::new();

            if (is_callable($count)) {
                $factory = $count($factory) ?? $factory;
            } else {
                $factory = $factory->count($count);
            }

            if ($configure !== null) {
                $factory = $configure($factory) ?? $factory;
            }

            $factory->create([
                'asset_id' => $asset->id,
            ]);
        });
    }
}
