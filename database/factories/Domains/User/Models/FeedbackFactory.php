<?php

namespace Database\Factories\Domains\User\Models;

use App\Contexts\Identity\Models\Feedback;
use App\Contexts\Identity\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Feedback>
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
