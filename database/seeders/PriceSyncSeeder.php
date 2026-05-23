<?php

namespace Database\Seeders;

use App\Domains\Asset\Services\PriceSyncService;
use Illuminate\Database\Seeder;

class PriceSyncSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('Syncing prices from Yahoo Finance...');

        $result = app(PriceSyncService::class)->syncAllStockAndEtfPrices();

        $this->command->info("Synced: {$result['synced']} — Errors: {$result['errors']}");

        foreach ($result['error_details'] as $detail) {
            $this->command->warn($detail);
        }
    }
}
