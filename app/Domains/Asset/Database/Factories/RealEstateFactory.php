<?php

namespace App\Domains\Asset\Database\Factories;

use App\Domains\Asset\Enums\AssetType;
use App\Domains\Asset\Models\RealEstate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RealEstate>
 */
class RealEstateFactory extends Factory
{
    protected $model = RealEstate::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'name' => fake()->city().' Property',
            'type' => AssetType::RealEstate->value,
        ];
    }
}
