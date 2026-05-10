<?php

namespace App\Domains\Asset\Database\Factories;

use App\Domains\Asset\Enums\AssetType;
use App\Domains\Asset\Models\Savings;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Savings>
 */
class SavingsFactory extends Factory
{
    protected $model = Savings::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'name' => fake()->company().' Account',
            'type' => AssetType::Savings->value,
        ];
    }
}
