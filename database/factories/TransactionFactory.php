<?php

namespace Database\Factories;

use App\Enums\AccountType;
use App\Models\Security;
use App\Models\Transaction;
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
            'date' => fake()->dateTimeBetween('-2 years', 'now'),
            'account_type' => fake()->randomElement(AccountType::cases()),
            'security_id' => Security::factory(),
            'quantity' => fake()->randomFloat(4, 1, 100),
            'unit_price' => fake()->randomFloat(4, 5, 500),
            'fees' => fake()->randomFloat(2, 0, 10),
            'notes' => fake()->optional(0.3)->sentence(),
        ];
    }

    public function pea(): static
    {
        return $this->state(fn (): array => [
            'account_type' => AccountType::Pea,
            'broker' => null,
        ]);
    }

    public function cto(): static
    {
        return $this->state(fn (): array => [
            'account_type' => AccountType::Cto,
            'broker' => fake()->randomElement(['Degiro', 'Trade Republic', 'Interactive Brokers', 'Boursorama']),
        ]);
    }

    public function livret(): static
    {
        return $this->state(fn (): array => [
            'account_type' => AccountType::Livret,
            'security_id' => null,
            'broker' => null,
            'quantity' => null,
            'unit_price' => null,
            'fees' => 0,
        ]);
    }
}
