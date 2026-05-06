<?php

namespace Database\Factories\Domains\Portfolio\Models;

use App\Domains\Portfolio\Models\AllocationProfile;
use App\Domains\Portfolio\Models\AllocationProfileItem;
use App\Domains\Security\Models\Security;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AllocationProfileItem>
 */
class AllocationProfileItemFactory extends Factory
{
    protected $model = AllocationProfileItem::class;

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
