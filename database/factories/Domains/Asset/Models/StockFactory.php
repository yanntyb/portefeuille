<?php

namespace Database\Factories\Domains\Asset\Models;

use App\Domains\Asset\Enums\AssetType;
use App\Domains\Asset\Models\Stock;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Stock>
 */
class StockFactory extends Factory
{
    protected $model = Stock::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        $countryPrefixes = ['FR', 'US', 'DE', 'LU', 'IE'];
        $prefix = fake()->randomElement($countryPrefixes);
        $digits = fake()->numerify('##########');
        $isin = $prefix.$digits;

        return [
            'isin' => $isin,
            'ticker' => fake()->randomAscii(),
            'name' => fake()->company().' Stock',
            'type' => AssetType::Stock,
        ];
    }
}
