<?php

namespace Database\Factories\Domains\Portfolio\Models;

use App\Contexts\Identity\Models\User;
use App\Domains\Portfolio\Models\Wallet;
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
            'user_id' => auth()?->id() ?? User::factory()->create()->id,
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
