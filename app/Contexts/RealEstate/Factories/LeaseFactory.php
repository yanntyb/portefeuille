<?php

namespace App\Contexts\RealEstate\Factories;

use App\Contexts\RealEstate\Models\Lease;
use App\Contexts\RealEstate\Models\Property;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Lease> */
class LeaseFactory extends Factory
{
    protected $model = Lease::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'property_id' => Property::factory(),
            'monthly_rent' => fake()->randomFloat(2, 400, 1200),
            'start_date' => fake()->date(),
            'end_date' => null,
        ];
    }

    /** Bail en cours, démarré il y a un an. */
    public function ongoing(): static
    {
        return $this->state(fn (): array => [
            'start_date' => now()->subYear()->startOfMonth()->toDateString(),
            'end_date' => null,
        ]);
    }
}
