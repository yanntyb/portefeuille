<?php

namespace App\Contexts\RealEstate\Factories;

use App\Contexts\RealEstate\Models\Property;
use App\Contexts\RealEstate\Models\PropertyValuation;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<PropertyValuation> */
class PropertyValuationFactory extends Factory
{
    protected $model = PropertyValuation::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'property_id' => Property::factory(),
            'date' => fake()->date(),
            'value' => fake()->randomFloat(2, 80000, 350000),
        ];
    }
}
