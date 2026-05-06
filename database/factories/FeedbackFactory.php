<?php

namespace Database\Factories;

use App\Domains\User\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\Feedback>
 */
class FeedbackFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => auth()->id() ?? User::factory(),
            'subject' => fake()->sentence(4),
            'body' => fake()->paragraph(),
        ];
    }
}
