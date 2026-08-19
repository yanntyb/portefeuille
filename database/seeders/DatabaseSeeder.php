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
        User::factory()->admin()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        $this->call(InstrumentCatalogSeeder::class);
        $this->call(EtfHistorySeeder::class);
        $this->call(BtcDcaSeeder::class);
        $this->call(GoldDcaSeeder::class);
    }
}
