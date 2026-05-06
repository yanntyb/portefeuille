<?php

namespace Database\Factories\Domains\Security\Models;

use App\Domains\Security\Models\Security;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Security>
 */
class SecurityFactory extends Factory
{
    protected $model = Security::class;

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
