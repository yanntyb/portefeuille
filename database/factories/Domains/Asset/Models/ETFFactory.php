<?php

namespace Database\Factories\Domains\Asset\Models;

use App\Domains\Asset\Enums\AssetType;
use App\Domains\Asset\Models\ETF;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ETF>
 */
class ETFFactory extends Factory
{
    protected $model = ETF::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        $countryPrefixes = ['FR', 'US', 'DE', 'LU', 'IE'];
        $prefix = fake()->randomElement($countryPrefixes);

        return [
            'isin' => $prefix.fake()->numerify('##########'),
            'name' => fake()->company().' ETF',
            'ticker' => fake()->lexify('????').'.PA',
            'type' => AssetType::ETF->value,
        ];
    }
}
