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
            'auto' => false,
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

    public function deposit(): static
    {
        return $this->state([
            'type' => TransactionType::Deposit,
            'asset_id' => null,
            'quantity' => null,
            'unit_price' => null,
            'amount' => fake()->randomFloat(2, 100, 5000),
        ]);
    }

    public function withdrawal(): static
    {
        return $this->deposit()->state(['type' => TransactionType::Withdrawal]);
    }

    public function dividend(): static
    {
        return $this->state([
            'type' => TransactionType::Dividend,
            'quantity' => null,
            'unit_price' => null,
            'amount' => fake()->randomFloat(2, 1, 200),
        ]);
    }

    /** Une ligne déduite par le système, que le recalcul réécrit. */
    public function auto(): static
    {
        return $this->state(['auto' => true]);
    }
}
