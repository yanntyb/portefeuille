<?php

namespace App\Contexts\Market\Factories;

use App\Contexts\Market\Models\Instrument;
use App\Contexts\Market\Models\Price;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Price>
 */
class PriceFactory extends Factory
{
    protected $model = Price::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        $close = fake()->randomFloat(4, 10, 500);

        return [
            'asset_id' => Instrument::factory(),
            'date' => fake()->date(),
            'open' => fake()->randomFloat(4, 10, 500),
            'high' => $close * fake()->randomFloat(2, 1.0, 1.05),
            'low' => $close * fake()->randomFloat(2, 0.95, 1.0),
            'close' => $close,
            'volume' => fake()->numberBetween(1000, 1000000),
        ];
    }
}
