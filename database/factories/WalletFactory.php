<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Wallet>
 */
class WalletFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'user_id' => auth()->id() ?? User::factory(),
            'name' => fake()->randomElement(['PEA', 'CTO', 'Livret']),
        ];
    }

    public function pea(): static
    {
        return $this->state(['name' => 'PEA']);
    }

    public function cto(): static
    {
        return $this->state(['name' => 'CTO']);
    }

    public function livret(): static
    {
        return $this->state(['name' => 'Livret']);
    }
}
