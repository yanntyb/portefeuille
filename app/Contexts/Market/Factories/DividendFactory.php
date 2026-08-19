<?php

namespace App\Contexts\Market\Factories;

use App\Contexts\Market\Models\Dividend;
use App\Contexts\Market\Models\Instrument;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Dividend>
 */
class DividendFactory extends Factory
{
    protected $model = Dividend::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'asset_id' => Instrument::factory(),
            'ex_date' => fake()->date(),
            'amount_per_share' => fake()->randomFloat(6, 0.01, 5),
        ];
    }
}
