<?php

namespace Database\Factories\Domains\Security\Models;

use App\Domains\Security\Enums\Sector;
use App\Domains\Security\Models\Security;
use App\Domains\Security\Models\SecuritySector;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SecuritySector>
 */
class SecuritySectorFactory extends Factory
{
    protected $model = SecuritySector::class;

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
