<?php

namespace Database\Factories\Domains\User\Models;

use App\Domains\User\Models\Feedback;
use App\Domains\User\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Domains\User\Models\Feedback>
 */
class FeedbackFactory extends Factory
{
    protected $model = Feedback::class;

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
