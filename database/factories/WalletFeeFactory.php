<?php

namespace Database\Factories;

use App\Enums\CurrencyModificationUnit;
use App\Enums\FeeScope;
use App\Enums\FrequencyUnit;
use App\Models\Wallet;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\WalletFee>
 */
class WalletFeeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'wallet_id' => Wallet::factory(),
            'name' => $this->faker->word(),
            'value' => $this->faker->numberBetween(1, 100),
            'unit' => CurrencyModificationUnit::Currency,
            'scope' => null,
            'frequency' => FrequencyUnit::Annual,
        ];
    }
}
