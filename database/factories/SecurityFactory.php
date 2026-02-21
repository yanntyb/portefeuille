<?php

namespace Database\Factories;

use App\Models\Security;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Security>
 */
class SecurityFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        $countryPrefixes = ['FR', 'US', 'DE', 'LU', 'IE'];
        $prefix = fake()->randomElement($countryPrefixes);
        $digits = fake()->numerify('##########');
        $isin = $prefix.$digits;

        return [
            'isin' => $isin,
            'name' => fake()->company().' '.fake()->randomElement(['ETF', 'Fund', 'Index', 'Stock']),
        ];
    }
}
