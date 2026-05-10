<?php

namespace App\Domains\Asset\Database\Factories;

use App\Domains\Asset\Enums\AssetType;
use App\Domains\Asset\Models\Bond;
use App\Domains\Asset\Models\BondAssetInfo;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Bond>
 */
class BondFactory extends Factory
{
    protected $model = Bond::class;

    public function configure(): static
    {
        return $this->afterCreating(function (Bond $bond) {
            BondAssetInfo::create([
                'asset_id' => $bond->id,
                'isin' => 'XS'.fake()->numerify('##############'),
                'ticker' => fake()->lexify('????'),
            ]);
        });
    }

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'name' => fake()->company().' Bond',
            'type' => AssetType::Bond->value,
        ];
    }
}
