<?php

namespace Database\Factories;

use App\Domains\Security\Models\Security;
use App\Domains\Security\Models\SecurityPrice;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SecurityPrice>
 */
class SecurityPriceFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        $close = fake()->randomFloat(4, 10, 500);

        return [
            'security_id' => Security::factory(),
            'date' => fake()->date(),
            'open' => fake()->randomFloat(4, 10, 500),
            'high' => $close * fake()->randomFloat(2, 1.0, 1.05),
            'low' => $close * fake()->randomFloat(2, 0.95, 1.0),
            'close' => $close,
            'volume' => fake()->numberBetween(1000, 1000000),
        ];
    }
}
