<?php

namespace App\Contexts\RealEstate\Factories;

use App\Contexts\RealEstate\Models\Lease;
use App\Contexts\RealEstate\Models\RentException;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<RentException> */
class RentExceptionFactory extends Factory
{
    protected $model = RentException::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'lease_id' => Lease::factory(),
            'month' => now()->startOfMonth()->toDateString(),
            'amount_override' => 0,
            'note' => null,
        ];
    }
}
