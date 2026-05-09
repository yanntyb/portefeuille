<?php

namespace Database\Factories\Domains\Asset\Models;

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
            'isin' => fake()->numerify('###############'),
            'name' => fake()->bank().' '.'Account',
            'ticker' => 'SAV',
            'type' => AssetType::Savings->value,
        ];
    }
}
