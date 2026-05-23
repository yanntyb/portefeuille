<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // TODO: Create User model and uncomment
        // $user = User::factory()->admin()->create([
        //     'name' => 'Test User',
        //     'email' => 'test@example.com',
        // ]);
        //
        // $this->call(TransactionSeeder::class, parameters: ['user' => $user]);
        // $this->call(FeedbackSeeder::class, parameters: ['user' => $user]);

        $this->call(PriceSyncSeeder::class);
    }
}
