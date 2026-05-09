<?php

namespace Database\Factories\Domains\Asset\Models;

use App\Domains\Asset\Models\AssetPrice;
use App\Domains\Security\Models\Security;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AssetPrice>
 */
class AssetPriceFactory extends Factory
{
    protected $model = AssetPrice::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        $close = fake()->randomFloat(4, 10, 500);

        return [
            'asset_id' => Security::factory(),
            'date' => fake()->date(),
            'open' => fake()->randomFloat(4, 10, 500),
            'high' => $close * fake()->randomFloat(2, 1.0, 1.05),
            'low' => $close * fake()->randomFloat(2, 0.95, 1.0),
            'close' => $close,
            'volume' => fake()->numberBetween(1000, 1000000),
        ];
    }
}
