<?php

namespace Database\Seeders;

use App\Contexts\Identity\Enums\Role;
use App\Contexts\Identity\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        User::query()->firstOrCreate(
            ['email' => 'test@example.com'],
            [
                'name' => 'Test User',
                'password' => 'password',
                'role' => Role::Admin,
                'email_verified_at' => now(),
            ],
        );

        $this->call(InstrumentCatalogSeeder::class);
        $this->call(EtfHistorySeeder::class);
        $this->call(BtcDcaSeeder::class);
        $this->call(GoldDcaSeeder::class);
        $this->call(DividendDemoSeeder::class);
        $this->call(RealEstateDemoSeeder::class);
    }
}
