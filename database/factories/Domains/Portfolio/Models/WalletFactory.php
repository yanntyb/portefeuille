<?php

namespace Database\Factories\Domains\Portfolio\Models;

use App\Domains\Portfolio\Models\Wallet;
use App\Domains\User\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Domains\Portfolio\Models\Wallet>
 */
class WalletFactory extends Factory
{
    protected $model = Wallet::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
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
