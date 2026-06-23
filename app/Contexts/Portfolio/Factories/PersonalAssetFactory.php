<?php

namespace App\Contexts\Portfolio\Factories;

use App\Contexts\Portfolio\Enums\PersonalAssetType;
use App\Contexts\Portfolio\Models\PersonalAsset;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PersonalAsset>
 */
class PersonalAssetFactory extends Factory
{
    protected $model = PersonalAsset::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'name' => fake()->words(2, true),
            'type' => PersonalAssetType::Savings,
        ];
    }

    public function ofType(PersonalAssetType $type): static
    {
        return $this->state(['type' => $type]);
    }
}
