<?php

namespace App\Contexts\RealEstate\Factories;

use App\Contexts\Identity\Models\User;
use App\Contexts\RealEstate\Models\Property;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Property> */
class PropertyFactory extends Factory
{
    protected $model = Property::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => fake()->streetName(),
            'address' => fake()->address(),
            'acquisition_date' => fake()->date(),
            'acquisition_price' => fake()->randomFloat(2, 80000, 300000),
            'acquisition_fees' => fake()->randomFloat(2, 5000, 25000),
        ];
    }
}
