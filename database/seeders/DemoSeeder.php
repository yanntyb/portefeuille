<?php

namespace Database\Seeders;

use App\Domains\User\Enums\Role;
use App\Domains\User\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DemoSeeder extends Seeder
{
    public function run(): User
    {
        $user = User::firstOrCreate(
            ['email' => 'demo@example.com'],
            [
                'name' => 'Démo',
                'password' => Str::random(32),
                'role' => Role::User,
            ],
        );

        $user->transactions()->delete();
        $user->wallets()->delete();

        $this->call(TransactionSeeder::class, parameters: ['user' => $user]);
        $this->call(FeedbackSeeder::class, parameters: ['user' => $user]);

        return $user;
    }
}
