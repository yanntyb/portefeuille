<?php

namespace App\Contexts\Portfolio\Factories;

use App\Contexts\Identity\Models\User;
use App\Contexts\Market\Models\Instrument;
use App\Contexts\Portfolio\Enums\TransactionType;
use App\Contexts\Portfolio\Models\Transaction;
use App\Contexts\Portfolio\Models\Wallet;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Transaction>
 */
class TransactionFactory extends Factory
{
    protected $model = Transaction::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'wallet_id' => Wallet::factory(),
            'asset_id' => Instrument::factory(),
            'date' => now()->subMonth(),
            'type' => TransactionType::Buy,
            'quantity' => fake()->randomFloat(4, 1, 100),
            'unit_price' => fake()->randomFloat(4, 10, 500),
            'fees' => 0,
        ];
    }

    public function buy(): static
    {
        return $this->state(['type' => TransactionType::Buy]);
    }

    public function sell(): static
    {
        return $this->state(['type' => TransactionType::Sell]);
    }
}
