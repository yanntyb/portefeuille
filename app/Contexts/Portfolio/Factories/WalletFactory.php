<?php

namespace App\Contexts\Portfolio\Factories;

use App\Contexts\Identity\Models\User;
use App\Contexts\Portfolio\Enums\AccountType;
use App\Contexts\Portfolio\Models\Wallet;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Wallet>
 */
class WalletFactory extends Factory
{
    protected $model = Wallet::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => fake()->unique()->words(2, true),
            'account_type' => AccountType::Cto,
            'opened_at' => null,
        ];
    }

    /** Un PEA ouvert il y a plus de cinq ans : l'enveloppe restreinte, sa maturité franchie. */
    public function pea(): static
    {
        return $this->state(fn (): array => [
            'name' => 'PEA',
            'account_type' => AccountType::Pea,
            'opened_at' => now()->subYears(7)->toDateString(),
        ]);
    }

    public function cto(): static
    {
        return $this->state(fn (): array => [
            'name' => 'CTO',
            'account_type' => AccountType::Cto,
            'opened_at' => null,
        ]);
    }
}
