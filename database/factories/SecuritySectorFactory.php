<?php

namespace Database\Factories;

use App\Enums\Sector;
use App\Models\Security;
use App\Models\SecuritySector;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SecuritySector>
 */
class SecuritySectorFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'security_id' => Security::factory(),
            'sector' => fake()->randomElement(Sector::cases()),
            'weight' => fake()->randomFloat(6, 0.01, 0.5),
        ];
    }
}
