<?php

namespace Database\Seeders;

use App\Domains\Asset\Models\Assets\Asset;
use App\Domains\Asset\Services\PriceSyncService;
use Illuminate\Database\Seeder;

class PriceSyncSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('Syncing Stock and ETF prices from Yahoo Finance...');

        $assetsWithTicker = Asset::query()
            ->whereIn('type', ['stock', 'etf'])
            ->whereNotNull('ticker')
            ->get();

        $this->command->info("Found {$assetsWithTicker->count()} assets with ticker...");

        $result = app(PriceSyncService::class)->syncAssets($assetsWithTicker);

        $this->command->info("Synced: {$result['synced']} — Errors: {$result['errors']}");

        if (! empty($result['error_details'])) {
            foreach ($result['error_details'] as $detail) {
                $this->command->warn($detail);
            }
        }
    }
}
