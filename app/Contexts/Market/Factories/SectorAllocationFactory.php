<?php

namespace App\Contexts\Market\Factories;

use App\Contexts\Market\Enums\Sector;
use App\Contexts\Market\Models\SectorAllocation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SectorAllocation>
 */
class SectorAllocationFactory extends Factory
{
    protected $model = SectorAllocation::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'sector' => Sector::cases()[array_rand(Sector::cases())],
            'weight' => $this->faker->randomFloat(2, 0, 1),
        ];
    }
}
