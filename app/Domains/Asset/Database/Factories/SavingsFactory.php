<?php

namespace App\Domains\Asset\Database\Factories;

use App\Domains\Asset\Enums\AssetType;
use App\Domains\Asset\Models\Savings;
use App\Domains\Asset\Models\SavingsAssetInfo;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Savings>
 */
class SavingsFactory extends Factory
{
    protected $model = Savings::class;

    public function configure(): static
    {
        return $this->afterCreating(function (Savings $savings) {
            SavingsAssetInfo::create([
                'asset_id' => $savings->id,
            ]);
        });
    }

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'name' => fake()->company().' Account',
            'type' => AssetType::Savings->value,
        ];
    }
}
