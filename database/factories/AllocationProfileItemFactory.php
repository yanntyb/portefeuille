<?php

namespace Database\Factories;

use App\Models\AllocationProfile;
use App\Models\AllocationProfileItem;
use App\Models\Security;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AllocationProfileItem>
 */
class AllocationProfileItemFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'allocation_profile_id' => AllocationProfile::factory(),
            'security_id' => Security::factory(),
            'target_percentage' => fake()->randomFloat(2, 5, 60),
        ];
    }
}
