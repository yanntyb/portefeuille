<?php

namespace Database\Factories;

use App\Enums\TransactionType;
use App\Domains\Security\Models\Security;
use App\Models\Transaction;
use App\Domains\User\Models\User;
use App\Models\Wallet;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Transaction>
 */
class TransactionFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'user_id' => auth()->id() ?? User::factory(),
            'wallet_id' => Wallet::factory(),
            'date' => fake()->dateTimeBetween('-2 years', 'now'),
            'security_id' => Security::factory(),
            'quantity' => fake()->randomFloat(4, 1, 100),
            'unit_price' => fake()->randomFloat(4, 5, 500),
            'type' => TransactionType::Buy,
            'fees' => fake()->randomFloat(2, 0, 10),
            'notes' => fake()->optional(0.3)->sentence(),
        ];
    }

    public function pea(): static
    {
        return $this->state(fn (): array => [
            'wallet_id' => auth()->check()
                ? Wallet::firstOrCreate(['user_id' => auth()->id(), 'name' => 'PEA'])->id
                : Wallet::factory()->pea(),
            'broker' => null,
        ]);
    }

    public function cto(): static
    {
        return $this->state(fn (): array => [
            'wallet_id' => auth()->check()
                ? Wallet::firstOrCreate(['user_id' => auth()->id(), 'name' => 'CTO'])->id
                : Wallet::factory()->cto(),
            'broker' => fake()->randomElement(['Degiro', 'Trade Republic', 'Interactive Brokers', 'Boursorama']),
        ]);
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
            'security_id' => null,
            'broker' => null,
            'quantity' => null,
            'unit_price' => null,
            'fees' => 0,
        ]);
    }
}
