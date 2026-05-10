<?php

namespace App\Domains\Asset\Factories;

use App\Domains\Asset\Models\AssetSector;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AssetSector>
 */
class AssetSectorFactory extends Factory
{
    protected $model = AssetSector::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            //
        ];
    }
}
