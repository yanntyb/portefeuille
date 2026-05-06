<?php

namespace Database\Factories;

use App\Models\AllocationProfile;
use App\Domains\User\Models\User;
use App\Models\Wallet;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AllocationProfile>
 */
class AllocationProfileFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'name' => fake()->words(2, true),
            'wallet_id' => fake()->optional(0.5)->randomElement(
                Wallet::query()->pluck('id')->all()
            ),
            'user_id' => auth()->id() ?? User::factory(),
        ];
    }

    public function pea(): static
    {
        return $this->state(fn (): array => [
            'wallet_id' => Wallet::factory()->pea(),
        ]);
    }

    public function cto(): static
    {
        return $this->state(fn (): array => [
            'wallet_id' => Wallet::factory()->cto(),
        ]);
    }

    public function global(): static
    {
        return $this->state(fn (): array => [
            'wallet_id' => null,
        ]);
    }
}
