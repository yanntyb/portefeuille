<?php

namespace Database\Factories\Domains\Asset\Models;

use App\Domains\Asset\Enums\AssetType;
use App\Domains\Asset\Models\Bond;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Bond>
 */
class BondFactory extends Factory
{
    protected $model = Bond::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'isin' => 'XS'.fake()->numerify('##############'),
            'name' => fake()->company().' '.'Bond',
            'ticker' => fake()->lexify('????'),
            'type' => AssetType::Bond->value,
        ];
    }
}
