<?php

namespace Database\Factories\Domains\Portfolio\Models;

use App\Domains\Portfolio\Enums\CurrencyModificationUnit;
use App\Domains\Portfolio\Enums\FrequencyUnit;
use App\Domains\Portfolio\Models\Wallet;
use App\Domains\Portfolio\Models\WalletFee;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Domains\Portfolio\Models\WalletFee>
 */
class WalletFeeFactory extends Factory
{
    protected $model = WalletFee::class;

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
            'frequency' => FrequencyUnit::Yearly,
        ];
    }
}
