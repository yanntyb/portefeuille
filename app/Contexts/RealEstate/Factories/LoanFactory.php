<?php

namespace App\Contexts\RealEstate\Factories;

use App\Contexts\RealEstate\Models\Loan;
use App\Contexts\RealEstate\Models\Property;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Loan> */
class LoanFactory extends Factory
{
    protected $model = Loan::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'property_id' => Property::factory(),
            'principal' => fake()->randomFloat(2, 50000, 250000),
            'annual_rate' => fake()->randomFloat(5, 0.01, 0.045),
            'term_months' => fake()->randomElement([180, 240, 300]),
            'start_date' => fake()->date(),
            'monthly_insurance' => fake()->randomFloat(2, 10, 60),
        ];
    }
}
