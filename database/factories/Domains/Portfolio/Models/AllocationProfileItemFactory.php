<?php

namespace Database\Factories\Domains\Portfolio\Models;

use App\Domains\Asset\Models\Asset;
use App\Domains\Portfolio\Models\AllocationProfile;
use App\Domains\Portfolio\Models\AllocationProfileItem;
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
            'asset_id' => Asset::factory(),
            'target_percentage' => fake()->randomFloat(2, 5, 60),
        ];
    }
}
