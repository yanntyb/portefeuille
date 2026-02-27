<?php

namespace Database\Factories;

use App\Enums\AccountType;
use App\Models\AllocationProfile;
use App\Models\User;
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
            'account_type' => fake()->optional(0.5)->randomElement(AccountType::cases()),
            'user_id' => auth()->id() ?? User::factory(),
        ];
    }

    public function pea(): static
    {
        return $this->state(fn (): array => [
            'account_type' => AccountType::Pea,
        ]);
    }

    public function cto(): static
    {
        return $this->state(fn (): array => [
            'account_type' => AccountType::Cto,
        ]);
    }

    public function global(): static
    {
        return $this->state(fn (): array => [
            'account_type' => null,
        ]);
    }
}
