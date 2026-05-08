<?php

namespace Database\Factories\Domains\Portfolio\Models;

use App\Domains\Portfolio\Enums\TransactionType;
use App\Domains\Portfolio\Models\Transaction;
use App\Domains\Portfolio\Models\Wallet;
use App\Domains\Security\Models\Security;
use App\Domains\User\Models\User;
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
            'user_id' => auth()->id() ?? User::factory()->create()->id,
            'wallet_id' => Wallet::factory(),
            'date' => fake()->dateTimeBetween('-2 years', 'now'),
            'asset_id' => Security::factory(),
            'quantity' => fake()->randomFloat(4, 1, 100),
            'unit_price' => fake()->randomFloat(4, 5, 500),
            'type' => TransactionType::Buy,
            'fees' => fake()->randomFloat(2, 0, 10),
            'notes' => fake()->optional(0.3)->sentence(),
        ];
    }

    public function pea(): static
    {
        return $this->state(function (): array {
            $userId = auth()?->id();
            if (! $userId) {
                $userId = User::factory()->create()->id;
            }

            return [
                'wallet_id' => Wallet::firstOrCreate(['user_id' => $userId, 'name' => 'PEA'])->id,
                'user_id' => $userId,
                'broker' => null,
            ];
        });
    }

    public function cto(): static
    {
        return $this->state(function (): array {
            $userId = auth()?->id();
            if (! $userId) {
                $userId = User::factory()->create()->id;
            }

            return [
                'wallet_id' => Wallet::firstOrCreate(['user_id' => $userId, 'name' => 'CTO'])->id,
                'user_id' => $userId,
                'broker' => fake()->randomElement(['Degiro', 'Trade Republic', 'Interactive Brokers', 'Boursorama']),
            ];
        });
    }

    public function sell(): static
    {
        return $this->state(fn (): array => [
            'type' => TransactionType::Sell,
        ]);
    }

    public function livret(): static
    {
        return $this->state(fn (): array => [
            'wallet_id' => auth()->check()
                ? Wallet::firstOrCreate(['user_id' => auth()->id(), 'name' => 'Livret'])->id
                : Wallet::factory()->livret(),
            'asset_id' => null,
            'broker' => null,
            'quantity' => null,
            'unit_price' => null,
            'fees' => 0,
        ]);
    }
}
