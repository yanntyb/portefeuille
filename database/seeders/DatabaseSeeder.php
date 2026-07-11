<?php

namespace Database\Seeders;

use App\Contexts\Identity\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $user = User::factory()->admin()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        // TODO: Create Wallet/Portfolio models
        // $this->call(TransactionSeeder::class, parameters: ['user' => $user]);
        // $this->call(FeedbackSeeder::class, parameters: ['user' => $user]);

        $this->call(DashboardDemoSeeder::class);
        $this->call(EtfHistorySeeder::class);
    }
}
