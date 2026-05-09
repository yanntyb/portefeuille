<?php

namespace Database\Factories\Domains\Asset\Models;

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
            'isin' => fake()->numerify('###############'),
            'name' => fake()->city().' '.'Property',
            'ticker' => fake()->lexify('????'),
            'type' => AssetType::RealEstate->value,
        ];
    }
}
