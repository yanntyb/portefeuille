<?php

namespace Database\Seeders;

use App\Models\Feedback;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class FeedbackSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(User $user): void
    {
        Feedback::factory()
            ->count(5)
            ->for($user)
            ->create();
    }
}
